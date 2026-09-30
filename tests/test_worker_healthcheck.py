"""A health check must never request or accept a queued job."""

from http.server import BaseHTTPRequestHandler, HTTPServer
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import threading
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "worker"))
from worker import codex_thread, prepare_branch


class WorkerHealthcheckTest(unittest.TestCase):
    def test_thread_id_and_branch_require_explicit_approval(self):
        thread_id = "01a0efe4-1c0b-7462-98d5-26650e463bc6"
        self.assertEqual(codex_thread(json.dumps({"type": "thread.started", "thread_id": thread_id})), thread_id)
        with self.assertRaises(RuntimeError):
            codex_thread(json.dumps({"type": "thread.started", "thread_id": thread_id}), "00000000-0000-0000-0000-000000000001")
        with tempfile.TemporaryDirectory() as directory:
            checkout = Path(directory)
            subprocess.run(["git", "init", "-q", "-b", "worker-test", str(checkout)], check=True)
            request = {"branch_recommendation": {"create_new": True, "suggested_name": "fix/topic"}}
            with self.assertRaisesRegex(RuntimeError, "awaits approval"):
                prepare_branch(checkout, request)
            self.assertEqual(subprocess.check_output(["git", "-C", str(checkout), "branch", "--show-current"], text=True).strip(), "worker-test")
            prepare_branch(checkout, request | {"branch_approval": "APPROVED"})
            self.assertEqual(subprocess.check_output(["git", "-C", str(checkout), "branch", "--show-current"], text=True).strip(), "fix/topic")

    def test_healthcheck_registers_and_heartbeats_without_polling(self):
        class Handler(BaseHTTPRequestHandler):
            def do_POST(self):
                self.server.calls.append(("POST", self.path))
                self.rfile.read(int(self.headers.get("Content-Length", "0")))
                body = b'{"status":"ONLINE"}'
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)

            def do_GET(self):
                self.server.calls.append(("GET", self.path))
                self.send_error(500, "Health check fetched a job")

            def log_message(self, *args):
                pass

        server = HTTPServer(("127.0.0.1", 0), Handler)
        server.calls = []
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        try:
            with tempfile.TemporaryDirectory() as directory:
                root = Path(directory)
                checkout = root / "checkout"
                subprocess.run(["git", "init", "-q", "-b", "worker-test", str(checkout)], check=True)
                token = root / "worker.token"
                token.write_text("a" * 64)
                os.chmod(token, 0o600)
                config = root / "config.json"
                config.write_text(json.dumps({
                    "server": f"http://127.0.0.1:{server.server_port}",
                    "worker_uuid": "00000000-0000-0000-0000-000000000001",
                    "token_file": str(token),
                    "projects": {"test-project": {
                        "path": str(checkout),
                        "environment": "development",
                        "codex_binary": "/usr/bin/true",
                    }},
                }))
                worker = Path(__file__).resolve().parents[1] / "worker" / "worker.py"
                result = subprocess.run(
                    [sys.executable, str(worker), "--config", str(config), "--healthcheck"],
                    capture_output=True, text=True, timeout=10,
                )
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertIn("no jobs fetched", result.stdout)
                self.assertEqual(server.calls, [
                    ("POST", "/api/workers/register"),
                    ("POST", "/api/workers/heartbeat"),
                ])
        finally:
            server.shutdown()
            server.server_close()
            thread.join(timeout=2)


if __name__ == "__main__":
    unittest.main()
