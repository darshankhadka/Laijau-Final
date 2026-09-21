# Laijau Showroom Local Print Agent (Production Showroom Edition)

Lightweight, zero-dependency background print agent for Showroom POS.
Enables instant, silent 80mm thermal receipt printing from Laijau's shared-hosting cloud installation with **zero browser print dialogs**.

---

## Authoritative Configuration Rule
> **ALL showroom hardware configuration is managed directly in the Laijau Admin Panel (`Admin → Settings → Hardware`).**
>
> **No showroom employee should ever need to edit `config.json`, Python files, `.env`, or database records manually.**

---

## Quick Setup (Zero Manual Config)

### Step 1: Generate Pairing Code in Admin Panel
1. Log into Laijau Admin (`https://laijau.com/intadmin` or local dev).
2. Go to **Settings → Hardware & Print Agents**.
3. Under your station (e.g. `Showroom Counter 1`), click **Pair Print Agent**.
4. You will see a 10-minute pairing code (e.g. `LJ-8K4P-29QX`).

### Step 2: Pair the Showroom PC
On the showroom PC, run the pair command:
```bash
python3 laijau_print_agent.py --pair LJ-8K4P-29QX --server https://laijau.com/api/v1/print-agent
```
The agent will:
- Authenticate with the server.
- Obtain its secure device token.
- Download assigned hardware specs (printer LAN IP/port or USB path, scanner burst settings, drawer kick).
- Cache configuration locally in `agent_state.json` for offline internet interruption resilience.

### Step 3: Run the Agent
Run the continuous background daemon:
```bash
python3 laijau_print_agent.py --daemon
```

---

## Hardware Connection Modes

1. **Network / LAN Thermal Printer (Standard Showroom Setup)**:
   - Configure printer IP (e.g. `192.168.1.200`) and TCP port (`9100`) in **Admin → Settings → Hardware → Printers**.
   - The Print Agent automatically connects and streams raw ESC/POS commands to the printer socket.
2. **Direct USB Thermal Printer**:
   - Configure USB device path (e.g. `/dev/usb/lp0`) in the Admin Panel.
   - The Print Agent writes directly to the character device.
3. **Cash Drawer Solenoid**:
   - Connected via standard RJ11/RJ12 cable to the back of the thermal receipt printer.
   - Automatically kicks after cash sale or on-demand from the Admin Panel.
4. **Barcode Scanner (USB HID)**:
   - Works via standard keyboard emulation.
   - Burst threshold (default 40ms) separates instant hardware barcode scans from manual typing.

---

## Self-Test Command

To test connection, credentials, and print a physical hardware test slip:
```bash
python3 laijau_print_agent.py --test
```

To test without physical printer attached:
```bash
python3 laijau_print_agent.py --test --dry-run
```

---

## Production Auto-Start

### Windows Showroom PC
1. Press `Win + R`, type `shell:startup`, and press Enter.
2. Create a shortcut running `pythonw.exe laijau_print_agent.py --daemon`.

### Linux Showroom PC (systemd)
Create `/etc/systemd/system/laijau-print-agent.service`:
```ini
[Unit]
Description=Laijau Showroom POS Print Agent
After=network.target

[Service]
Type=simple
User=showroom
WorkingDirectory=/path/to/local_print_agent
ExecStart=/usr/bin/python3 laijau_print_agent.py --daemon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```
Enable and start the service:
```bash
sudo systemctl enable --now laijau-print-agent
```
