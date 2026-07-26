# IntakeMGR

**Service intake, scheduling, and job management for service businesses.**
Self-hosted, by [ScriptGain](https://scriptgain.com).

## Who it's for

Local service businesses that run on jobs rather than shifts: pool service,
plumbing, HVAC, electrical, landscaping, appliance repair, cleaning, pest control,
handyman work. One truck or a dozen.

## What it does

**Take the work in**
Customers request service from your site, or you log the call at the desk. Every
request lands in one inbox instead of across a phone, an inbox, and a notepad.

**Answer without losing the thread**
Tickets with a reply thread, internal notes only staff can see, assignment, and
status. The customer sees their side; your notes stay yours.

**Quote it and get a decision**
Estimates the customer accepts or declines from a link on their phone, with the
answer recorded against the job.

**Book it properly**
Public self-serve booking against real technician availability, with two-way
calendar sync for Google, Microsoft, Apple, and Nylas — so a tech's dentist
appointment blocks the slot automatically. Customers reschedule or cancel from a
signed link without calling you.

**Do the job**
Work orders with line items, an assignee, and a schedule. Group related jobs under
a project when one engagement spans several visits.

**Get paid**
Invoice straight from the completed work order and take the card yourself. Card
processing runs through **your** Stripe or Authorize.Net account — we never touch
the money and never take a cut.

**Give customers a place to look**
A portal for their requests, tickets, appointments, work orders, estimates, and
invoices, so "can you send that again" stops being a phone call.

**Run the front of house**
A public site with your services, prices, booking, and a searchable help centre,
themeable without touching code.

## Current state

**Version 0.2.0** and in active development. Intake, tickets, estimates, work
orders, projects, invoicing with both payment providers, calendar sync, public
booking with self-serve reschedule and cancel, and the customer portal are all built
and verified on a live instance.

Known gaps: booking confirmation emails are not wired up yet, and the demo booking
types are all assigned to the admin account rather than to real technicians.

## Install

Point a fresh Debian or Ubuntu server at your domain and run, as root:

```
DOMAIN=service.example.com SSL=1 EMAIL=you@example.com ./deploy/install-master.sh
```

Then open `https://your.domain/setup` to create the first account and enter your
licence key.

*(A one-line hosted installer is published for the older ScriptGain products and is
not available for IntakeMGR yet.)*

## Where things live

| Surface | Path |
| --- | --- |
| Public site and booking | `/` |
| Office panel | `/admin` |
| Customer portal | `/account` |
| First-run setup | `/setup` |

## Running it

Business identity, services and prices, tax, payment credentials, calendar
connections, and email are all edited in the panel rather than in files on the
server.

Maintenance tasks from the command line:

| Command | What it does |
| --- | --- |
| `php artisan shop:bootstrap` | Seeds baseline settings and defaults. Safe to re-run. |
| `php artisan shop:license <key>` | Sets or re-checks your licence key. |
| `php artisan shop:housekeeping` | Prunes stale records. Runs nightly. |
| `php artisan shop:themes-install` | Installs the bundled themes. |
| `php artisan calendar:sync` | Pushes and pulls staff calendar events. |
| `php artisan app:update` | Applies a signed release. |
| `php artisan db-backup:run` | Backs up the database. |
| `php artisan firewall:clear` | Gets you back in if an IP rule locks you out. |

*The command prefix is `shop:` rather than `intake:` — IntakeMGR was built on
ScriptGain's commerce platform and kept its internal namespace. Cosmetic, but worth
knowing before you type the command that doesn't exist.*

## Requirements

A Linux server with PHP 8.3 and MySQL or MariaDB.

## Calendar connections, if you use them

Each provider needs an app you register yourself, and the credentials go in the
panel, not in a config file. Two things that waste an afternoon otherwise: a
provider's green "Test" button only confirms the fields are filled, not that the
secret is correct — only a real Connect proves that. And Google secrets look like
`GOCSPX-…` while Azure secrets contain a `~`; pasting one into the other's box
produces an unhelpful `invalid_client`.

## Licensing

One activation per licence by default, validated against
`https://scriptgain.com/v1`. See
[scriptgain.com/products/intakemgr](https://scriptgain.com/products/intakemgr).
