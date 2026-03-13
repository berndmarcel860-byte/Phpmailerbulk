# PhpMailerBulk

A professional PHP bulk email sending system with a full admin backend.

---

## Features

- **Admin Backend** – secure login, dashboard with live statistics
- **Lead Management** – add leads manually or import from CSV; supports 3 lead formats:
  - `name, email`
  - `first_name, last_name, email`
  - `first_name, last_name, email, platform, amount, date`
- **Multi-SMTP Rotation** – add unlimited SMTP accounts; the system rotates through them (send N emails per account, then switch to the next)
- **Anti-Spam Features** – configurable pause between batches, word/phrase replacement filter, professional email headers
- **Campaign Management** – create campaigns, assign templates and lead groups, control send rate
- **HTML Email Templates** – full HTML editor with live preview; variable substitution from lead data (`{{first_name}}`, `{{last_name}}`, `{{email}}`, `{{platform}}`, `{{amount}}`, `{{date}}`)
- **Statistics** – per-campaign stats: sent, failed, success rate, per-SMTP breakdown, CSV export of logs
- **Anti-Spam Word Filters** – admin-managed list of spam-trigger words replaced automatically before sending
- **Cron Support** – background sending script for large lists

---

## Requirements

- PHP 8.0+ (recommended 8.1+)
- MySQL 5.7+
- Composer

---

## Installation

### 1. Clone & Install Dependencies

```bash
git clone https://github.com/berndmarcel860-byte/Phpmailerbulk.git
cd Phpmailerbulk
composer install
```

### 2. Run the Setup Wizard

Open your browser and navigate to:

```
http://your-domain/install.php
```

Fill in your database credentials and create an admin account. The wizard will:
- Create the MySQL database and all tables
- Seed default anti-spam rules
- Generate a secure `config.php`

### 3. Log In

After installation, go to `http://your-domain/login.php` and log in with your admin credentials.

---

## Usage

### Leads
1. Go to **Leads** → **Import CSV** to bulk-import leads
2. Or use **Add Lead** to add them one by one

CSV column names are auto-detected from the header row. Supported aliases:
- First name: `first_name`, `firstname`, `name`, `nombre`
- Last name: `last_name`, `lastname`, `surname`, `apellido`
- Email: `email`, `e-mail`, `mail`, `correo`
- Platform: `platform`, `plataforma`, `source`
- Amount: `amount`, `cantidad`, `value`, `importe`
- Date: `date`, `fecha`, `datetime`

### SMTP Accounts
1. Go to **SMTP Accounts** → **Add SMTP**
2. Add as many accounts as you like
3. Set **Emails/Session** – how many emails to send before rotating to the next account

### Templates
1. Go to **Templates** → **New Template**
2. Write your HTML email with variables like `{{first_name}}`, `{{platform}}`, `{{amount}}`
3. Use **Preview HTML** to see how it looks

### Campaigns
1. Go to **Campaigns** → **New Campaign**
2. Select a template and lead group
3. Set emails-per-SMTP and pause seconds (anti-spam)
4. Click **Send** (▶) to start sending

### Anti-Spam Rules
Go to **Anti-Spam** to manage word replacement rules. These are applied to subject and body before each email is sent.

### Background Sending (Cron)

For large lists, use the cron script instead of the browser:

```bash
# Run all pending campaigns
php cron/send_campaign.php

# Run a specific campaign
php cron/send_campaign.php 5
```

Add to crontab to check every minute:
```
* * * * * php /path/to/cron/send_campaign.php >> /var/log/phpmailerbulk.log 2>&1
```

---

## Security Notes

- `config.php` is excluded from Git (listed in `.gitignore`)
- Passwords are stored as bcrypt hashes
- All user input is escaped with `htmlspecialchars` / PDO prepared statements
- SMTP passwords are stored in the database (consider encrypting at rest for production)

---

## License

MIT