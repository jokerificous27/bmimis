# User Manual

## Signing in

Open the system (e.g. `http://bmi.test`) and sign in with the account issued by the administrator. After sign-in you land on the dashboard for your role. Change your password and enable two-factor authentication under **My Account / Settings → Security**.

## PNP Personnel

| Menu | What you can do |
|---|---|
| My Dashboard | Current BMI and classification, ideal body weight, this month's weigh-in status, weight chart, assigned intervention |
| My BMI Profile | Personal information, current BMI, progress charts by year, assessment history |
| My Monthly Monitoring | January–December weights with month-to-month change; print your Individual BMI Monitoring Form |
| My Assessment History | Every submitted/certified assessment; print your BMI Form |
| My Notifications | Reminders and updates from the Health Service |

You can only ever see your own records.

## Health Service Encoder / Administrator

### Registering personnel
*Personnel Directory → Add personnel.* The employee number must be unique; a warning appears if the same name and birth date already exist. Optionally create a login account. Changing unit, rank or position creates a new assignment-history entry (enter the effective date and order number); old BMI records keep their original unit and rank.

Bulk registration: *Personnel Directory → Import* (download the template, upload, review the validation report, then import the valid rows). Historical monthly weights can be imported the same way.

### Encoding a BMI assessment (BMI Form)
1. *BMI Assessments → New assessment* (or *New assessment* on a profile).
2. Search and select the personnel — rank, name, age, sex and office fill in automatically.
3. Enter date, height (cm), weight (kg), waist, hip, wrist. Results (BMI, IBW, weight to lose, PNP and WHO classification, suggested package) update live.
4. Optional: photos (right/front/left) for the monitoring form; signatories.
5. **Save as draft**, or **Preview and submit**. If an assessment already exists for that date you must confirm superseding it (the old one stays in history).
6. A Health Service administrator (not the encoder) opens the assessment and clicks **Certify correct**, or **Return** it to draft with a reason.

To correct a submitted/certified assessment, open it and click **Correct**; a reason is mandatory and is kept in the audit trail.

### Weigh-in Station (fastest way to record monthly weights)
1. Give every personnel a **weigh-in QR card**: *Monthly Weight Monitoring → Print QR cards* (per unit), or *Print → Weigh-in QR card* on a profile. Personnel can also show their QR from *My Dashboard* on their phone. The QR contains a random code only, not their name or number. If a card is lost, use *Print → Re-issue QR code* on the profile; the old card stops working. Codes issued from now on use a compact format that stays readable when small in a full-body photo; codes issued earlier keep working, and re-issuing one switches it to the compact format.
2. Open *Weigh-in Station* on a phone or tablet.
3. The personnel opens **My Weigh-in QR** on their own phone (full-screen QR, brightness up) and places the phone in the holder beside the scale display, screen facing the camera. A printed card in the holder works too.
4. The personnel stands on the scale. Staff stand about 1.5–2 m in front and open the **Smart camera**, framing the **whole body with the scale display and the phone clearly visible** (see *How to take the weigh-in photo* on the station page). Tap the scale display on the screen once: from then on that spot is read and the camera exposes for it (remembered on the phone). Two labels on the camera show the status: **Personnel identified** (QR code read) and **Weight picked up** (the same clear reading in three frames in a row). Only then can the photo be taken; with *Capture automatically when ready* on, it is taken by itself. If a label stays grey or amber, tilt the phone slightly against glare or step closer. **Take photo** (the phone's own camera app, no live status) remains available as a fallback.
5. **Automatic mode** (a weight reader chosen in System Settings, *Save automatically* on). The default reader, **On the phone**, reads the red LED digits on the device itself: no API key, no cost, no photo sent outside. The optional **AI** reader handles difficult photos better but needs an Anthropic API key. when the QR code is recognised and the scale display is read clearly, the weight is saved immediately. A green banner shows who was saved and the weight, with a *Wrong? Correct it* link, and the station is ready for the next person. The station instead asks staff to check (then **Confirm and save**) when the reading is unclear, the display can't be read, nobody was identified, or the weight differs from the last weigh-in by more than the *significant monthly change* setting.
   Without AI reading, type the weight shown on the scale and tap **Confirm and save**.

Photo tips: keep the QR card flat and fully visible, avoid glare on the scale display, and take the photo when the scale has settled. Use the phone's normal camera resolution: the display digits must be clearly legible in the photo, so stand close enough that the display is not tiny. Very bright or washed-out photos make the red digits hard to read. On scales with several windows (weight, price, total) only the leftmost, weight window is read. If the phone is not sure about any digit or the decimal point, it never guesses: staff type the weight instead. Set *System Settings → Decimals shown on the scale display* to match the scale (default: two, as in 59.80); readings with another number of decimals are never trusted.

The photo is kept privately with the monthly record (camera icon on the year sheet). If the person was already weighed this month, the station says so and does not save twice; users who may correct records (Health Service Administrator) can **Replace this month's weight** after a re-weigh, and the old value stays in the audit trail. With no card, search by name instead. The **Smart camera** needs the site to run on HTTPS (or `localhost`); browsers block the live camera on plain `http://` addresses such as `http://192.168.0.110:8000`. **Take photo** works without it.

### Monthly weight monitoring
*Monthly Weight Monitoring* shows all personnel for a month. Type weights for those not yet recorded, set the measurement date and click **Save entered weights**. Filter by unit or "Not yet recorded". Open a person's year sheet to record a single month or **Correct** an entry (correction rights and a reason required). Only one weight per month is allowed.

**Large BMI change: update waist, hip and wrist?** When a monthly weigh-in gives a BMI that differs from the personnel's last assessment by at least the *Significant BMI change* setting (System Settings; default 2.0 BMI points, an operational setting and not an official PNP standard), the Health Service staff who encode assessments (encoders and administrators) are asked whether the waist, hip and wrist should be measured again, and can enter the new measurements right away. The question appears as a notification, on the dashboard (*BMI changed: update waist, hip, wrist?*), on the person's year sheet and, at the Weigh-in Station, right after the weigh-in. Updating is optional:
- **Yes, update measurements**: type the new values (the last ones are filled in, so anything left unchanged is carried over). They are saved as a new assessment dated on the weigh-in, with the weigh-in weight and the height on record; the earlier assessment is not changed.
- **No update needed**: the previous measurements stay on record and the question is closed.

Only users who may both correct records and encode assessments (Health Service Administrator by default) can answer; other staff see that the administrators have been asked. Encoding a full BMI assessment for the person also closes the question.

### Interventions
*Intervention Management* lists ongoing interventions and personnel whose classification needs intervention but have none. **Assign intervention** → choose package, dates and target weight. On the intervention page: **Record follow-up** (weight, notes, next date) and **Complete / discontinue** with an outcome.

### Compliance, unit monitoring and analytics
- *Compliance Monitoring*: compliance per unit for a month, missing personnel, trend, and **Send reminders**.
- *Unit Monitoring*: one unit's statistics and personnel status.
- *BMI Analytics*: year-over-year comparison, consistent weight trends, reassessment follow-up list, and data quality (incomplete profiles, missing measurements, possible duplicates, unusual entries).

### Reports
*Reports* (Individual, Monthly, Annual, Unit, Summary): choose the report, set filters, then **Print preview** (A4; use *Print / Save as PDF* for a PDF) or **Export Excel**. Official forms: Individual BMI Form, Individual BMI Monitoring Form, Consolidated Profile.

**BMI Upload File (Excel template)** (*Monthly Reports*): choose the year, month and (optionally) unit, then **Export Excel**. The file follows the BMI TEMPLATE layout for import into the receiving system: one sheet named *Sheet1* with the columns AccountNumber, ApplicableYear, ApplicableMonth, Height (cm), Weight (kg), Hip (cm), Wrist (cm), Waist (cm), and one row per personnel weighed in that month. AccountNumber is the employee number; ApplicableMonth is the month number (1–12); height and weight come from the monthly weigh-in; hip, wrist and waist come from the latest assessment on or before the end of that month (blank when never measured). Use **Print preview** to check the rows first.

## Unit Representative

Your dashboard and lists are limited to your assigned unit: unit statistics, monthly compliance, personnel pending monitoring or needing follow-up, and unit reports (compliance and pending lists).

## Administrator

- **User Management** — create accounts, assign roles and units, link personnel, activate/deactivate (the last Super Administrator cannot be removed).
- **Roles & Permissions** — tick/untick permissions per role.
- **Unit / Rank Management** — reference lists.
- **BMI Standards** — create a new version, set effective dates, policy reference and bands (BMI from ≥ / to <, optional age and sex, intervention requirement, default package). Overlapping bands are rejected. Untick *Provisional* once official values are encoded.
- **Intervention Packages** — official descriptions, requirements, activities, nutrition guidance and follow-up interval.
- **Audit Trail** — read-only log of sign-ins, changes (with previous and new values), certifications, prints and exports.
- **System Settings** — letterhead, BMI precision and IBW method, validation ranges, monitoring deadline and reminders, compliance target, alert thresholds, default signatories, email notifications, and the Weigh-in Station **weight reader**: *On the phone* (default, free, offline), *AI* (sends each photo, which shows the personnel, to the Anthropic API; needs `ANTHROPIC_API_KEY` and Data Protection Officer clearance) or *Off*.

> BMI results and recommendations are informational and subject to Health Service evaluation; the system is not a medical diagnostic tool.
