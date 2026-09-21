#!/usr/bin/env python3
"""
Laijau Local Print Agent (Production Showroom Edition)
======================================================
Lightweight, zero-dependency background print agent for Showroom POS.
Authoritative hardware configuration lives in the Laijau database.

Showroom staff NEVER edit config.json, Python code, or .env files.

Pairing:
    python3 laijau_print_agent.py --pair LJ-8K4P-29QX [--server http://127.0.0.1:8080/api/v1/print-agent]

Normal Execution:
    python3 laijau_print_agent.py --daemon     # Continuous background polling & heartbeat
    python3 laijau_print_agent.py --test       # Connection and hardware test
    python3 laijau_print_agent.py --poll-once  # Process queued jobs once and exit
    python3 laijau_print_agent.py --dry-run    # Dry-run mode without physical hardware
"""

import argparse
import base64
import json
import logging
import os
import platform
import socket
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

AGENT_VERSION = "2.0.0"
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
STATE_FILE_PATH = os.path.join(BASE_DIR, "agent_state.json")
BOOTSTRAP_CONFIG_PATH = os.path.join(BASE_DIR, "config.json")

# Configure Logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger("LaijauPrintAgent")


class LaijauPrintAgent:
    def __init__(self, state_path=None, config_path=None, server_override=None, dry_run=False):
        self.state_path = state_path or STATE_FILE_PATH
        self.config_path = config_path or BOOTSTRAP_CONFIG_PATH
        self.dry_run = dry_run

        self.api_url = "http://127.0.0.1:8080/api/v1/print-agent"
        self.device_token = ""
        self.station_id = "showroom_counter_1"
        self.poll_interval = 1.5
        self.hardware_config = {}
        self.config_version = 0
        self.last_heartbeat_time = 0

        # Load state / configuration
        self.load_runtime_state()

        if server_override:
            self.api_url = server_override.rstrip("/")

    # --------------------------------------------------------------------------
    # Configuration & Persistence
    # --------------------------------------------------------------------------
    def load_runtime_state(self):
        """
        Loads authoritative runtime state.
        Priority:
        1. agent_state.json (created via pairing or server refresh)
        2. config.json (bootstrap fallback if state doesn't exist yet)
        """
        if os.path.exists(self.state_path):
            try:
                with open(self.state_path, "r", encoding="utf-8") as f:
                    state = json.load(f)
                    self.api_url = state.get("api_url", self.api_url).rstrip("/")
                    self.device_token = state.get("device_token", self.device_token)
                    self.station_id = state.get("station_id", self.station_id)
                    self.poll_interval = float(state.get("poll_interval_seconds", 1.5))
                    self.hardware_config = state.get("hardware_config", {})
                    self.config_version = state.get("config_version", 0)
                    return
            except Exception as e:
                logger.warning(f"Could not read state file ({e}). Falling back to bootstrap.")

        if os.path.exists(self.config_path):
            try:
                with open(self.config_path, "r", encoding="utf-8") as f:
                    cfg = json.load(f)
                    self.api_url = cfg.get("api_url", self.api_url).rstrip("/")
                    self.device_token = cfg.get("device_token", self.device_token)
                    self.station_id = cfg.get("station_id", self.station_id)
                    self.poll_interval = float(cfg.get("poll_interval_seconds", 1.5))
                    return
            except Exception as e:
                logger.warning(f"Could not read bootstrap config ({e}).")

    def save_runtime_state(self):
        """Persist authoritative state to agent_state.json for offline resilience."""
        data = {
            "api_url": self.api_url,
            "device_token": self.device_token,
            "station_id": self.station_id,
            "poll_interval_seconds": self.poll_interval,
            "hardware_config": self.hardware_config,
            "config_version": self.config_version,
            "last_updated": time.strftime("%Y-%m-%d %H:%M:%S"),
        }
        try:
            with open(self.state_path, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2)
            logger.info(f"Updated local hardware cache in {os.path.basename(self.state_path)}")
        except Exception as e:
            logger.error(f"Failed to write state file: {e}")

    # --------------------------------------------------------------------------
    # Pairing Workflow
    # --------------------------------------------------------------------------
    def pair_with_server(self, pairing_code, server_url=None):
        """
        Pair the agent using a 10-minute pairing code generated in the Admin UI.
        No manual secret key entry or file editing required.
        """
        if server_url:
            self.api_url = server_url.rstrip("/")

        endpoint = f"{self.api_url}/pair"
        payload = {
            "pairing_code": pairing_code.strip(),
            "hostname": socket.gethostname(),
            "os": f"{platform.system()} {platform.release()}",
            "agent_version": AGENT_VERSION,
        }

        logger.info("==================================================")
        logger.info(f" Pairing Print Agent with Laijau Server...")
        logger.info(f" Target Server: {self.api_url}")
        logger.info(f" Pairing Code : {pairing_code}")
        logger.info("==================================================")

        try:
            req = urllib.request.Request(
                endpoint,
                data=json.dumps(payload).encode("utf-8"),
                headers={
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "User-Agent": f"Laijau-Print-Agent/{AGENT_VERSION}",
                },
                method="POST",
            )
            with urllib.request.urlopen(req, timeout=12) as response:
                resp = json.loads(response.read().decode("utf-8"))

            if not resp.get("success"):
                logger.error(f"Pairing rejected: {resp.get('error')}")
                return False

            self.device_token = resp["device_token"]
            self.station_id = resp["station_id"]
            self.hardware_config = resp.get("hardware_config", {})
            self.config_version = self.hardware_config.get("config_version", time.time())

            # Save state immediately
            self.save_runtime_state()

            logger.info("==================================================")
            logger.info(" ✓ PRINT AGENT SUCCESSFULLY PAIRED!")
            logger.info(f" Station Code : {self.station_id}")
            logger.info(f" Station Name : {resp.get('station_name')}")
            logger.info(f" Hardware Spec: {len(self.hardware_config.get('printers', []))} printer(s) configured.")
            logger.info(" Credentials and hardware settings cached locally.")
            logger.info("==================================================")
            logger.info("You can now start the print daemon:")
            logger.info("   python3 laijau_print_agent.py --daemon")
            logger.info("==================================================")
            return True

        except urllib.error.HTTPError as e:
            err_msg = e.read().decode("utf-8", errors="ignore")
            logger.error(f"Pairing failed (HTTP {e.code}): {err_msg}")
            return False
        except Exception as e:
            logger.error(f"Network error during pairing: {e}")
            return False

    # --------------------------------------------------------------------------
    # API Communication & Offline Interruption Handling
    # --------------------------------------------------------------------------
    def _api_request(self, endpoint, method="GET", data=None):
        url = f"{self.api_url}/{endpoint.lstrip('/')}"
        headers = {
            "User-Agent": f"Laijau-Print-Agent/{AGENT_VERSION}",
            "Accept": "application/json",
            "X-Print-Agent-Key": self.device_token,
        }

        body = None
        if data is not None:
            body = json.dumps(data).encode("utf-8")
            headers["Content-Type"] = "application/json"

        req = urllib.request.Request(url, data=body, headers=headers, method=method)

        try:
            with urllib.request.urlopen(req, timeout=10) as response:
                resp_text = response.read().decode("utf-8")
                return json.loads(resp_text)
        except urllib.error.HTTPError as e:
            error_body = e.read().decode("utf-8", errors="ignore")
            logger.error(f"API HTTP {e.code} on {method} {url}: {error_body}")
            raise
        except urllib.error.URLError as e:
            # Temporary network interruption / offline mode
            logger.warning(f"Connection to {url} failed: {e.reason}. Local agent will retry automatically.")
            raise

    def send_heartbeat(self):
        """Send periodic telemetry heartbeat and check for hardware config changes."""
        payload = {
            "hostname": socket.gethostname(),
            "os": f"{platform.system()} {platform.release()}",
            "agent_version": AGENT_VERSION,
            "status": "online",
        }
        try:
            resp = self._api_request("heartbeat", method="POST", data=payload)
            server_version = resp.get("config_version", 0)
            if server_version and server_version > self.config_version:
                logger.info("Server hardware configuration updated. Fetching fresh config...")
                self.refresh_config()
            self.last_heartbeat_time = time.time()
            return True
        except Exception as e:
            logger.warning(f"Heartbeat failed (temporary connection issue): {e}")
            return False

    def refresh_config(self):
        """Fetch latest authoritative hardware configuration from server."""
        try:
            resp = self._api_request("config", method="GET")
            if resp.get("success") and resp.get("hardware_config"):
                self.hardware_config = resp["hardware_config"]
                self.config_version = self.hardware_config.get("config_version", time.time())
                self.save_runtime_state()
                logger.info("Hardware configuration refreshed and cached.")
        except Exception as e:
            logger.warning(f"Could not refresh hardware configuration: {e}")

    def check_health(self):
        """Verify API connectivity and station authentication."""
        endpoint = f"health?station_id={urllib.parse.quote(self.station_id)}"
        return self._api_request(endpoint, method="GET")

    def poll_job(self):
        """Poll server for next queued print job."""
        endpoint = f"jobs/poll?station_id={urllib.parse.quote(self.station_id)}"
        return self._api_request(endpoint, method="GET")

    def update_job_status(self, job_uuid, status, error_message=None):
        """Report completion or failure of a print job."""
        endpoint = f"jobs/{job_uuid}/status"
        payload = {"status": status}
        if error_message:
            payload["error"] = str(error_message)[:900]
        return self._api_request(endpoint, method="POST", data=payload)

    def trigger_test_job(self):
        """Request server to enqueue a hardware test receipt."""
        endpoint = "test-job"
        return self._api_request(endpoint, method="POST", data={"station_id": self.station_id})

    def discover_local_printers(self):
        """
        Scans local LAN and USB ports for thermal receipt printers.
        Reports discovered printers to the Laijau server so the Admin
        can adopt them in one click.
        """
        import concurrent.futures

        logger.info("==================================================")
        logger.info(" Scanning local network and USB for thermal printers...")
        logger.info("==================================================")
        discovered = []

        # 1. Check local USB printer device nodes (Linux)
        for usb_path in ["/dev/usb/lp0", "/dev/usb/lp1", "/dev/usb/lp2"]:
            if os.path.exists(usb_path):
                logger.info(f"✓ Discovered local USB printer device at {usb_path}")
                discovered.append({
                    "name": f"USB Thermal Printer ({usb_path})",
                    "connection_type": "usb",
                    "usb_device_path": usb_path,
                    "status": "available",
                })

        # 2. Probe port 9100 (standard raw ESC/POS port) on LAN
        candidate_ips = set()
        def_printer = self.hardware_config.get("default_receipt_printer") or {}
        if def_printer.get("network_ip"):
            candidate_ips.add(def_printer["network_ip"])

        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.connect(("8.8.8.8", 80))
            local_ip = s.getsockname()[0]
            s.close()
            prefix = ".".join(local_ip.split(".")[:3])
            for host in range(1, 255):
                candidate_ips.add(f"{prefix}.{host}")
        except Exception:
            for host in [200, 201, 202, 100, 101, 150, 192, 10, 20, 50]:
                candidate_ips.add(f"192.168.1.{host}")
                candidate_ips.add(f"192.168.0.{host}")

        def check_port(ip):
            try:
                sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                sock.settimeout(0.25)
                res = sock.connect_ex((ip, 9100))
                sock.close()
                if res == 0:
                    return ip
            except Exception:
                pass
            return None

        with concurrent.futures.ThreadPoolExecutor(max_workers=35) as executor:
            futures = {executor.submit(check_port, ip): ip for ip in candidate_ips}
            for fut in concurrent.futures.as_completed(futures):
                found = fut.result()
                if found:
                    logger.info(f"✓ Discovered LAN thermal printer at {found}:9100")
                    discovered.append({
                        "name": f"LAN Thermal Printer ({found}:9100)",
                        "connection_type": "network",
                        "network_ip": found,
                        "network_port": 9100,
                        "status": "available",
                    })

        logger.info(f"Discovery finished. Found {len(discovered)} printer(s).")

        # 3. Report to Laijau server
        if self.device_token:
            try:
                endpoint = "discovered-printers"
                resp = self._api_request(endpoint, method="POST", data={
                    "station_id": self.station_id,
                    "printers": discovered,
                })
                logger.info(f"✓ Reported to Laijau Admin: {resp.get('message')}")
            except Exception as e:
                logger.warning(f"Could not report discovered printers to server: {e}")

        return discovered

    # --------------------------------------------------------------------------
    # Hardware ESC/POS Streaming (Server-Config Driven)
    # --------------------------------------------------------------------------
    def print_raw_escpos(self, raw_bytes, printer_spec=None):
        """
        Send raw ESC/POS bytes directly to printer hardware.
        The printer configuration is dynamically provided by the server or cached.
        """
        if self.dry_run:
            logger.info(f"[DRY RUN] Received {len(raw_bytes)} bytes of ESC/POS commands. Hardware send simulated.")
            return True

        # Resolve target printer:
        # 1. Printer attached directly to job
        # 2. Station's default receipt printer from hardware config
        # 3. Fallback
        target_printer = printer_spec or self.hardware_config.get("default_receipt_printer") or {}

        p_type = target_printer.get("type") or target_printer.get("connection_type") or "network"
        p_type = p_type.lower()

        if p_type == "network":
            ip = target_printer.get("network_ip", "192.168.1.200")
            port = int(target_printer.get("network_port", 9100))
            return self._print_to_socket(ip, port, raw_bytes)

        elif p_type in ("usb", "usb_raw"):
            device_path = target_printer.get("usb_device_path", "/dev/usb/lp0")
            return self._print_to_usb_device(device_path, raw_bytes)

        elif p_type == "file":
            file_path = target_printer.get("file_path", "test_receipt.bin")
            with open(file_path, "wb") as f:
                f.write(raw_bytes)
            logger.info(f"Written {len(raw_bytes)} bytes to {file_path}")
            return True

        else:
            raise ValueError(f"Unsupported printer connection type: '{p_type}'")

    def _print_to_socket(self, ip, port, raw_bytes):
        logger.info(f"Connecting to LAN thermal printer at {ip}:{port}...")
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(7.0)
        try:
            sock.connect((ip, port))
            sock.sendall(raw_bytes)
            time.sleep(0.15)
            logger.info(f"✓ Successfully streamed {len(raw_bytes)} bytes to {ip}:{port}")
            return True
        finally:
            sock.close()

    def _print_to_usb_device(self, device_path, raw_bytes):
        logger.info(f"Writing to USB thermal printer at {device_path}...")
        if not os.path.exists(device_path):
            raise FileNotFoundError(f"USB printer device not found at {device_path}")

        with open(device_path, "wb") as dev:
            dev.write(raw_bytes)
            dev.flush()
        logger.info(f"✓ Successfully sent {len(raw_bytes)} bytes to {device_path}")
        return True

    # --------------------------------------------------------------------------
    # Job Processing Loop
    # --------------------------------------------------------------------------
    def process_single_job(self):
        """Poll once and process a single print job if available."""
        try:
            poll_resp = self.poll_job()
        except Exception:
            # Network issue handled by caller
            return False

        if not poll_resp.get("has_job") or not poll_resp.get("job"):
            return False

        job = poll_resp["job"]
        job_uuid = job["uuid"]
        idempotency_key = job.get("idempotency_key", "")
        printer_spec = job.get("printer") or job.get("payload_data", {}).get("printer")

        logger.info(f"--> Claimed print job: {job_uuid} ({idempotency_key})")
        if printer_spec:
            logger.info(f"    Target Printer: {printer_spec.get('name')} ({printer_spec.get('type')})")

        raw_b64 = job.get("raw_escpos_base64", "")
        if not raw_b64:
            logger.error(f"Job {job_uuid} has empty ESC/POS payload.")
            self.update_job_status(job_uuid, "failed", "Empty raw_escpos_base64 payload")
            return True

        try:
            raw_bytes = base64.b64decode(raw_b64)
            self.print_raw_escpos(raw_bytes, printer_spec=printer_spec)
            logger.info(f"✓ Print job {job_uuid} completed successfully.")
            self.update_job_status(job_uuid, "printed")
        except Exception as e:
            logger.error(f"✗ Failed printing job {job_uuid}: {e}")
            try:
                self.update_job_status(job_uuid, "failed", str(e))
            except Exception as update_err:
                logger.error(f"Failed reporting job failure to server: {update_err}")

        return True

    def run_daemon(self):
        """Continuous polling daemon with automatic heartbeat and offline reconnection."""
        logger.info(f"==================================================")
        logger.info(f" LAIJAU LOCAL PRINT AGENT v{AGENT_VERSION} (DAEMON)")
        logger.info(f" Station ID : {self.station_id}")
        logger.info(f" Server API : {self.api_url}")
        logger.info(f" Poll Period: {self.poll_interval}s")
        logger.info(f" Dry Run    : {self.dry_run}")
        logger.info(f"==================================================")

        # Initial check & heartbeat
        self.send_heartbeat()

        # Run initial network printer discovery in background thread
        import threading
        discovery_thread = threading.Thread(target=self.discover_local_printers, daemon=True)
        discovery_thread.start()

        consecutive_errors = 0
        heartbeat_interval = 30.0  # Send heartbeat every 30 seconds
        discovery_interval = 600.0 # Discover printers every 10 minutes
        last_discovery_time = time.time()

        while True:
            try:
                # Periodic Heartbeat
                now = time.time()
                if now - self.last_heartbeat_time >= heartbeat_interval:
                    self.send_heartbeat()

                if now - last_discovery_time >= discovery_interval:
                    last_discovery_time = now
                    t = threading.Thread(target=self.discover_local_printers, daemon=True)
                    t.start()

                # Process any pending jobs
                had_job = self.process_single_job()
                consecutive_errors = 0

                if had_job:
                    # If a job was processed, immediately check for the next one
                    continue

            except KeyboardInterrupt:
                logger.info("Print agent stopped by user.")
                break
            except Exception as e:
                consecutive_errors += 1
                backoff = min(15.0, self.poll_interval * (1.5 ** min(consecutive_errors, 5)))
                logger.warning(f"Temporary connection error ({e}). Retrying in {backoff:.1f}s...")
                time.sleep(backoff)
                continue

            time.sleep(self.poll_interval)

    def run_test(self):
        """Execute end-to-end self-test."""
        logger.info("==================================================")
        logger.info("   LAIJAU PRINT AGENT — HARDWARE & API SELF-TEST   ")
        logger.info("==================================================")

        # 1. Test API Health
        logger.info("Step 1: Testing API connection and authentication...")
        try:
            health = self.check_health()
            logger.info(f"✓ API Status: OK ({health.get('status')})")
            logger.info(f"  Station: {health.get('station_name')} ({health.get('station_id')})")
            logger.info(f"  Queue Stats: {health.get('queue')}")
        except Exception as e:
            logger.error(f"✗ API Connection failed: {e}")
            return False

        # 2. Trigger Server Test Job
        logger.info("Step 2: Requesting server to generate hardware test receipt...")
        try:
            test_resp = self.trigger_test_job()
            test_uuid = test_resp.get("job_uuid")
            logger.info(f"✓ Test job queued with UUID: {test_uuid}")
        except Exception as e:
            logger.error(f"✗ Failed enqueuing test job: {e}")
            return False

        # 3. Poll & Stream Test Job
        logger.info("Step 3: Polling and executing hardware print job...")
        time.sleep(0.5)
        success = self.process_single_job()
        if success:
            logger.info("==================================================")
            logger.info("✓ HARDWARE TEST COMPLETED SUCCESSFULLY!")
            logger.info("  Check physical thermal printer for test slip.")
            logger.info("==================================================")
            return True
        else:
            logger.error("✗ Test job could not be processed.")
            return False


def main():
    parser = argparse.ArgumentParser(description="Laijau Showroom Local Print Agent")
    parser.add_argument("--pair", help="Pair agent with showroom station using 10-minute pairing code (e.g. --pair LJ-8K4P-29QX)", default=None)
    parser.add_argument("--server", help="Server API URL (e.g. http://127.0.0.1:8080/api/v1/print-agent)", default=None)
    parser.add_argument("--test", help="Run end-to-end connection and hardware test", action="store_true")
    parser.add_argument("--daemon", help="Run in continuous background polling daemon mode", action="store_true")
    parser.add_argument("--poll-once", help="Poll once for pending jobs and exit", action="store_true")
    parser.add_argument("--discover", help="Scan LAN and USB for printers and report to Admin", action="store_true")
    parser.add_argument("--dry-run", help="Simulate printing without sending raw bytes to physical printer", action="store_true")

    args = parser.parse_args()

    agent = LaijauPrintAgent(server_override=args.server, dry_run=args.dry_run)

    if args.pair:
        ok = agent.pair_with_server(args.pair, server_url=args.server)
        sys.exit(0 if ok else 1)
    elif args.test:
        ok = agent.run_test()
        sys.exit(0 if ok else 1)
    elif args.discover:
        agent.discover_local_printers()
    elif args.poll_once:
        agent.process_single_job()
    elif args.daemon:
        agent.run_daemon()
    else:
        print("No mode specified. Options:")
        print("  --pair <CODE>    Pair print agent with station pairing code")
        print("  --daemon         Run continuous background agent")
        print("  --discover       Scan local network and USB for printers")
        print("  --test           Run hardware connection self-test")
        print("  --poll-once      Process queued jobs once and exit")
        parser.print_help()


if __name__ == "__main__":
    main()
