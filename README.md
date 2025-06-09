# 🔥 BurnerBox

**BurnerBox** is a lightweight, open-source, temporary email inbox system that works with Postfix and Apache2. It collects all incoming mail for a given domain and displays it on a simple, auto-refreshing web interface — perfect for dev testing, anonymous feedback, or short-term inboxes.

---

## ✨ Features

- 📥 Catch-all email support via Postfix (wildcard @domain)
- 📂 Supports `mbox` mail storage format
- 🎛️ Auto-generates random email addresses
- 📬 Filters inbox by email address
- 👀 Shows message previews with expandable bodies
- ⏱️ Auto-refreshes every 30 seconds
- 📄 Download emails as `.eml`
- 🧹 Auto-deletes messages after 24 hours
- 🔐 IP-based ACL (Apache2)
- 📜 Includes interactive install script
- 🔒 Optional HTTPS support with Let's Encrypt

---

## 🚀 Installation

1. Clone or download this repo:
   ```bash
   git clone https://github.com/yourusername/burnerbox.git
   cd burnerbox
   ```

2. Run the install script (requires sudo/root):
   ```bash
   sudo bash install.sh
   ```

3. Follow prompts to:
   - Set up your domain (e.g. `wcsd.io`)
   - Choose the install path (default: `/var/www/[domain]`)
   - Provide the target mailbox (must already exist)
   - Configure Apache and Postfix
   - Set admin email and allowed IPs
   - (Optional) Run Let's Encrypt for HTTPS

4. Access the inbox:
   ```
   http://yourdomain/
   ```

---

## 📁 File Structure

```
burnerbox/
├── index.php         # Main inbox viewer
├── install.sh        # Interactive installer
├── config.php        # Auto-generated site config
└── README.md         # This file
```

---

## 🧪 Example Usage

- Visit `http://yourdomain/` and a random inbox (e.g. `a7c8e9d2@wcsd.io`) is auto-generated
- Send an email to that address
- Page auto-refreshes every 30 seconds
- Expand messages, or download them as `.eml`
- Use `?debug=1` to view **all** messages (admin/debug only)

---

## ⚠️ Security Warning

- This tool is **unauthenticated**
- Do **not** expose it publicly unless behind a firewall or VPN
- Designed for internal/testing/dev use

---

## 📜 License

This project is licensed under the [MIT License](LICENSE).
