#!/usr/bin/env python3
"""Reference polling worker. Python 3.10+, standard library only."""
import argparse
import json
import os
from pathlib import Path
import shlex
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

ANALYSIS_SCHEMA = {
    "type": "object",
    "properties": {
        "probable_cause": {"type": "string"},
        "evidence": {"type": "array", "items": {"type": "string"}},
        "affected_components": {"type": "array", "items": {"type": "string"}},
        "risk": {"type": "string"},
        "proposed_solution": {"type": "string"},
        "suggested_subtasks": {"type": "array", "items": {"type": "string"}},
        "required_tests": {"type": "array", "items": {"type": "string"}},
        "missing_information": {"type": "array", "items": {"type": "string"}},
    },
    "required": ["probable_cause", "evidence", "affected_components", "risk", "proposed_solution", "suggested_subtasks", "required_tests", "missing_information"],
    "additionalProperties": False,
}


def call(config, token, method, path, payload=None):
    body = json.dumps(payload).encode() if payload is not None else None
    request = urllib.request.Request(config["server"].rstrip("/") + "/api" + path, data=body, method=method,
        headers={"Authorization": "Bearer " + token, "Content-Type": "application/json", "Accept": "application/json"})
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            return json.load(response)
    except urllib.error.HTTPError as error:
        message = error.read(2000).decode(errors="replace")
        raise RuntimeError(f"API {error.code}: {message}") from error


def git(path, *args):
    return subprocess.run(["git", "-C", str(path), *args], capture_output=True, text=True, check=True).stdout.strip()


def safe_project(config, slug):
    project = config.get("projects", {}).get(slug)
    if not project:
        raise RuntimeError(f"Project slug {slug} is not configured locally")
    path = Path(project["path"]).expanduser().resolve(strict=True)
    if not (path / ".git").exists():
        raise RuntimeError("Mapped path must be a Git checkout")
    if project.get("environment") != "development":
        raise RuntimeError("Only development checkouts are permitted")
    branch = git(path, "branch", "--show-current")
    if branch in ("main", "master", "production", "prod", ""):
        raise RuntimeError("Use a non-production working branch")
    return path, project


def run_job(config, token, job):
    path, local = safe_project(config, job["project_slug"])
    accepted = call(config, token, "POST", f"/jobs/{job['id']}/accept", {})
    execution_id = accepted["execution_id"]
    process = None
    try:
        call(config, token, "POST", f"/jobs/{execution_id}/progress", {"message": "Codex started"})
        if accepted["project_slug"] != job["project_slug"]:
            raise RuntimeError("Project slug changed during acceptance")
        analysis = accepted["type"] == "technical_analysis"
        if accepted["sandbox"] != ("read-only" if analysis else "workspace-write"):
            raise RuntimeError("Invalid sandbox policy")
        before = git(path, "status", "--porcelain=v1", "-uall")
        if analysis and before:
            raise RuntimeError("Analysis requires a clean checkout")
        with tempfile.TemporaryDirectory(prefix="dev-orchestrator-") as temp:
            output_path = Path(temp) / "last.txt"
            command = [local.get("codex_binary", "codex"), "exec", "--ephemeral", "--sandbox", accepted["sandbox"], "--output-last-message", str(output_path), "-C", str(path), "-"]
            if analysis:
                schema_path = Path(temp) / "analysis.schema.json"
                schema_path.write_text(json.dumps(ANALYSIS_SCHEMA))
                command[2:2] = ["--output-schema", str(schema_path)]
            prompt = ("Never deploy, change production, run migrations against production, delete data, or use real secrets. "
                      "If approval is needed, explain and stop.\n\n" + accepted["prompt"])
            process = subprocess.Popen(command, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                       text=True, cwd=path)
            deadline = time.monotonic() + config.get("job_timeout_seconds", 3600)
            input_text = prompt
            while True:
                try:
                    stdout, stderr = process.communicate(input=input_text, timeout=min(20, max(1, deadline - time.monotonic())))
                    break
                except subprocess.TimeoutExpired:
                    input_text = None
                    heartbeat = call(config, token, "POST", "/workers/heartbeat", {})
                    if heartbeat.get("cancel_requested"):
                        process.kill()
                        process.communicate()
                        raise RuntimeError("Requirement was cancelled")
                    if time.monotonic() >= deadline:
                        process.kill()
                        process.communicate()
                        raise RuntimeError("Codex job timed out")
            result = subprocess.CompletedProcess(command, process.returncode, stdout, stderr)
            summary = output_path.read_text(errors="replace") if output_path.exists() else "Codex did not produce a final message"
        after = git(path, "status", "--porcelain=v1", "-uall")
        if analysis and after != before:
            raise RuntimeError("Analysis modified checkout")
        modified = [line[3:] for line in after.splitlines() if line and line not in before.splitlines()]
        tests = []
        if not analysis and result.returncode == 0:
            allowed = set(local.get("allowed_test_commands", []))
            for test_command in accepted.get("test_commands", []):
                if test_command not in allowed:
                    tests.append({"command": test_command, "status": "DENIED"})
                    continue
                test = subprocess.run(shlex.split(test_command), cwd=path, capture_output=True, text=True, timeout=600)
                tests.append({"command": test_command, "status": "PASSED" if test.returncode == 0 else "FAILED", "output": (test.stdout + test.stderr)[-3000:]})
        payload = {"summary": summary[:4000], "stdout": result.stdout[-65536:], "stderr": result.stderr[-65536:],
                   "modified_files": modified, "branch": git(path, "branch", "--show-current"), "commit": git(path, "rev-parse", "HEAD"), "tests": tests}
        if analysis and result.returncode == 0:
            payload["result"] = json.loads(summary)
        success = result.returncode == 0 and all(test["status"] == "PASSED" for test in tests)
        if not success:
            payload["error"] = f"Codex exit {result.returncode} or tests failed"
        call(config, token, "POST", f"/jobs/{execution_id}/{'complete' if success else 'fail'}", payload)
    except Exception as error:
        if process is not None and process.poll() is None:
            process.kill()
            process.communicate()
        try:
            call(config, token, "POST", f"/jobs/{execution_id}/fail", {"summary": "Worker failed", "error": str(error)[:4000]})
        except Exception as report_error:
            print(f"Could not report failure: {report_error}", file=sys.stderr)
        raise


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", required=True)
    parser.add_argument("--once", action="store_true")
    args = parser.parse_args()
    config_path = Path(args.config).expanduser().resolve(strict=True)
    config = json.loads(config_path.read_text())
    token_path = Path(config["token_file"]).expanduser().resolve(strict=True)
    if token_path.stat().st_mode & 0o077:
        raise RuntimeError("Token file must be readable only by its owner (chmod 600)")
    token = token_path.read_text().strip()
    if len(token) < 32:
        raise RuntimeError("Worker token is invalid")
    call(config, token, "POST", "/workers/register", {"uuid": config["worker_uuid"], "agent_version": "reference-1", "environment": {"platform": sys.platform}})
    while True:
        try:
            call(config, token, "POST", "/workers/heartbeat", {})
            response = call(config, token, "GET", "/workers/jobs/next")
            if response.get("job"):
                run_job(config, token, response["job"])
        except Exception as error:
            print(f"Worker cycle failed: {error}", file=sys.stderr)
        if args.once:
            break
        time.sleep(config.get("poll_seconds", 20))


if __name__ == "__main__":
    main()
