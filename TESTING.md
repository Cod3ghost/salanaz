# Manual test checklist

Run these against a fresh install before go-live, and again after any change
that touches money, roles or documents.

Local preview: `npm run preview`, then <http://127.0.0.1:9400>. Every seeded
account uses the password `password`.

| Login | Role |
| --- | --- |
| `admin` | Administrator |
| `cofounder` | Co-Founder |
| `staff1`, `staff2` | Sales Staff |
| `client1`, `client2` | Client (approved) |
| `client3` | Client (pending approval) |

> The demo database resets on every preview restart. Finish a checklist in one
> session, or expect to start it again.

---

## 1. The full journey

The single most important path. Registration through to an allocation letter,
end to end. Roughly ten minutes.

| # | Step | Expected |
| --- | --- | --- |
| 1 | Sign out. Open an estate page and click **Reserve** on an available plot | Taken to registration, with a "Reserving: Plot …" banner naming the plot |
| 2 | Register with a real-looking Nigerian number, e.g. `0803 123 4567` | Redirected to sign-in with "Registration received" |
| 3 | Sign in as the new client | You see "Your account is being reviewed", **not** the dashboard |
| 4 | Try to reserve a plot from that account | Refused — a pending account holds no purchasing rights |
| 5 | Sign in as `cofounder` → **Salanaz → Approvals** | The new registration is listed with phone and location |
| 6 | Click **Approve** | Success notice; client disappears from the queue |
| 7 | **Salanaz → Clients**, assign the client to a sales officer | "Both parties have been notified" |
| 8 | Sign in as the client again | Full dashboard, with the assigned officer's name and phone in the sidebar |
| 9 | Reserve the plot and choose the **12 months** plan | Plot reserved; you land on the payment page asking for the deposit |
| 10 | Check the schedule under **View payment schedule** | 12 instalments; deposit + instalments equals the total exactly |
| 11 | Upload a proof (JPG/PNG/PDF) for the deposit amount | "Payment proof received" |
| 12 | Dashboard | Payment shows **Pending verification**; balance unchanged |
| 13 | As `cofounder` → **Salanaz → Payments**, open **View proof** | The uploaded file opens |
| 14 | Click **Verify** | Success; client notified |
| 15 | Back as the client | Progress bar moved; **Download receipt** link present; instalment 1 shows as paid if the deposit covered it |
| 16 | Open the receipt | PDF with client name, amount as `NGN …`, plot, total paid and outstanding |
| 17 | Pay the remaining balance and verify it | Plot flips to **Sold** |
| 18 | Client dashboard | **Allocation letter** button appears |
| 19 | Open the allocation letter | PDF naming the allottee, estate, plot number, size and total consideration |

---

## 2. Client

Sign in as `client1`.

- [ ] Dashboard shows plots held, total paid and outstanding
- [ ] Assigned sales officer is named, with a working `tel:` and `mailto:` link
- [ ] **Browse plots** reaches the estates listing
- [ ] Estate page filters work: location, minimum size, maximum budget
- [ ] A filter combination with no matches shows the empty state, **not** every estate
- [ ] Reserving shows all four plans with correct per-month figures
- [ ] The 24-month plan discloses the 10% carrying charge
- [ ] Reserving a plot someone else just took shows "no longer available"
- [ ] Payment page shows the corporate bank details and the "never pay an individual" warning
- [ ] Uploading a `.txt` renamed to `.png` is **rejected**
- [ ] Uploading a file over 5 MB is **rejected**
- [ ] Payment history lists reference, date, amount, method and status
- [ ] **View proof** and **Download receipt** both open
- [ ] Signing out and hitting `/dashboard/` shows the sign-in prompt

### Client — things that must fail

- [ ] `/wp-admin/` redirects to the dashboard, never into the admin
- [ ] `?page=salanaz-payments`, `salanaz-clients`, `salanaz-analytics` all return **403**
- [ ] Another client's receipt URL returns **403**
- [ ] Editing the amount field to pay against a plot you do not hold is refused

---

## 3. Sales staff

Sign in as `staff1`.

- [ ] Dashboard lists only clients assigned to you
- [ ] Totals show clients, collected and outstanding
- [ ] **Open file** shows the client's plots, payment progress and history
- [ ] Contact, address and next-of-kin details are visible
- [ ] Logging a note with a type and a follow-up date saves
- [ ] The note appears in the timeline with type, author and timestamp
- [ ] The follow-up appears in the **Follow-ups** panel on the overview
- [ ] A follow-up dated today or earlier is highlighted and counted in the badge
- [ ] An empty note is rejected

### Sales staff — things that must fail

- [ ] Opening `?view=client&client={id}` for a client assigned to another officer
      shows "not assigned to you", and **leaks no name, phone or payment data**
- [ ] All Salanaz admin screens return **403**
- [ ] The plots list in wp-admin returns **403** — staff may browse inventory
      publicly but not edit pricing
- [ ] You cannot verify a payment or create an account anywhere in the interface

---

## 4. Co-Founder

Sign in as `cofounder`.

### Approvals

- [ ] **Approvals** lists pending registrations with contact and location
- [ ] Approving sends a welcome and removes the row
- [ ] Rejecting with a reason records it; the client sees the reason on their dashboard
- [ ] A rejected account **cannot sign in**

### Staff

- [ ] Creating a staff account emails a one-time password
- [ ] Duplicate email, invalid email, bad phone and missing name are each rejected
- [ ] The new officer can sign in and sees an empty client list

### Assignment

- [ ] Assigning a client notifies both the officer and the client
- [ ] Reassigning moves the client; the previous assignment is retained as history

### Payments

- [ ] The queue shows client, plot, amount, payer note and a proof link
- [ ] Verifying advances the schedule and updates the client's balance
- [ ] Full payment flips the plot to **Sold** and issues the allocation letter
- [ ] Rejecting a payment that was already verified **gives back the credit** —
      the plan reopens, instalments revert, the plot returns to Reserved

### Inventory

- [ ] A plot's estate, number, size, price and availability are all editable
- [ ] The plots list shows Estate, Size, Price and Availability columns
- [ ] Setting a plot back to **Available** clears its held-by marker
- [ ] Estate address, title document and coordinates save and appear on the front end

### Analytics

- [ ] Collected counts **verified** payments only
- [ ] Outstanding reflects what is owed on held plots
- [ ] Sales-by-officer figures match what each officer sees on their own dashboard
- [ ] Inventory counts per estate match the plots list
- [ ] Overdue table names the client, the officer and the days late

### Automation

- [ ] **Run the sweep now** reports counts for 7-day, 1-day and overdue
- [ ] Running it a second time reports **0 / 0 / 0** — nothing is sent twice
- [ ] The overdue client, their officer and the co-founders each receive a message

### Co-Founder — things that must fail

- [ ] **Settings** returns **403** — payment keys are administrator-only
- [ ] Verifying an already-verified payment is refused

---

## 5. Security spot-checks

Do these before go-live, and after any change to uploads or documents.

- [ ] Copy a payment proof's stored path from the database and request it
      directly by URL. **It must not be served.** On nginx this needs an
      explicit rule — see the README
- [ ] A proof URL without a nonce returns **403**; signed out returns **401**
- [ ] Registration is rate-limited after five attempts from one address
- [ ] Posting `salanaz_action=approve_client` directly, as a client, changes nothing
- [ ] `wp-admin` is unreachable for clients and staff
- [ ] The activity log records approvals, rejections, assignments, verifications
      and note additions, with the acting user

---

## 6. Paystack — needs live keys

**Not yet exercised against the real API.** The signature, idempotency,
amount-tampering and currency guards are tested; the two live calls
(`transaction/initialize`, `transaction/verify`) are not, because this build has
no keys and the local environment has no outbound networking.

Work through this once with **test keys** before switching to live:

- [ ] Enter `sk_test` / `pk_test` under **Settings** and enable card payment
- [ ] Paste the webhook URL into Paystack → Settings → API Keys & Webhooks
- [ ] As a client, choose **Continue to Paystack** for a small amount
- [ ] You reach Paystack checkout with the correct amount in Naira
- [ ] Complete the payment with a Paystack test card
- [ ] You return to the dashboard with "Payment received and confirmed"
- [ ] The transaction shows **Verified**, method *Card / transfer*
- [ ] A receipt is generated
- [ ] In Paystack's dashboard, the webhook delivery shows **200**
- [ ] Re-send that webhook from Paystack. The balance **must not change**
- [ ] Abandon a checkout instead of paying. Nothing is credited
- [ ] Only then swap in `sk_live` / `pk_live` and repeat with a small real amount

---

## 7. Go-live checklist

- [ ] SMTP configured and a test message received at a Gmail address
- [ ] `DISABLE_WP_CRON` set, with a server cron hitting `wp-cron.php`
- [ ] Paystack live keys in `wp-config.php`, not the database
- [ ] Real bank details set for the manual payment route
- [ ] Payment proof storage confirmed unreachable by URL
- [ ] Allocation letter wording replaced with your own contract language,
      including RC number and signatory
- [ ] Demo estates, plots and test accounts removed
- [ ] Co-founder passwords changed from `password`, and 2FA enabled
- [ ] Privacy policy and terms pages written
- [ ] A real backup running, covering both the database and the private
      uploads directory
