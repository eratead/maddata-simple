# Privacy Policy

**Effective date:** 10 May 2026
**Last updated:** 10 May 2026

> **Drafting note (not part of the published document):** This is a starting draft tailored to what the MadData application actually does. It is **not legal advice** and should be reviewed by a qualified attorney — ideally one familiar with Israeli Privacy Protection Law (PPL) and the EU GDPR — before being published. Delete this note before publishing.

---

## 1. Who we are

MadData is a campaign management and reporting platform for digital advertising agencies and brands ("the Service"). The Service is operated by **eRate online measurement solutions ltd** (Israel), reachable at **support@erate.co.il**. In this policy, "we", "us", and "our" refer to eRate online measurement solutions ltd as the operator of MadData; "you" and "your" refer to the individual using the Service.

The Service is provided on a business-to-business basis. Accounts are created by an administrator from a customer organisation (an agency or brand), and each user receives credentials from that administrator. We do not offer self-service public registration to consumers.

## 2. What information we collect

### 2.1 Account information you provide
When an administrator creates your account, we store:

- Your name and email address.
- A salted hash of your password (never the password itself).
- The role assigned to you within your organisation (for example, Admin, Agency Manager, Viewer).
- Optionally, the secret used by your authenticator app for two-factor authentication (TOTP).

### 2.2 Google account information (only if you connect Google)
If you connect a Google account to use as your second factor, we store:

- The Google account's stable identifier (the OpenID Connect `sub` claim).
- The email address Google reports for that account.
- The timestamp of the connection.

We do **not** receive or store your Google password and we do **not** request access to your Gmail, Drive, Calendar, or any other Google services beyond the basic profile (email and name) that the OAuth `openid email profile` scopes provide. You can disconnect your Google account at any time from Settings → Sign-in methods, subject to keeping at least one second factor enabled on your account.

### 2.3 Activity and audit logs
For security and accountability we record actions taken in the Service: who logged in, who created or modified a campaign, who uploaded a report, and similar events. These logs include the user identifier, the action, the affected record, and a timestamp.

### 2.4 Session and technical data
When you use the Service we store an encrypted session cookie in your browser to keep you logged in, along with a CSRF protection token. Our web server also retains short-lived access logs (IP address, request path, user agent, response code) for operational and security purposes.

### 2.5 Customer business data
Your organisation uploads campaign data to the Service — campaign settings, daily and placement-level performance metrics (impressions, clicks, cost, viewability), creative files, audience definitions, and similar operational records. **This is your organisation's business data, not personal data of end consumers.** We process this data on behalf of your organisation, which is the data controller for it.

The Service is **not designed** to ingest personal data of end consumers. If your organisation uploads such data into the Service contrary to its intended use, your organisation is responsible for the legality of that processing.

### 2.6 AI assistant interactions
The Service includes an optional AI campaign-brief assistant that uses Anthropic's Claude API. When you interact with this feature, the brief text you write is sent to Anthropic for processing along with a system prompt describing available audience segments. We do not include your name, email, or any other personal identifiers in those requests. Anthropic processes the data subject to their own terms; see "Third parties" below.

## 3. How we use information

We use the data described above to:

- Authenticate you and keep your session secure.
- Provide the campaign management and reporting features of the Service.
- Maintain audit trails for accountability and security investigations.
- Send transactional emails relating to your account (for example, activity digest summaries you have opted in to).
- Diagnose problems, prevent abuse, and improve the Service.

We do **not** sell personal data, and we do **not** use your data for advertising or for training third-party AI models.

## 4. Third parties we share information with

We share data only with the following processors, and only to the extent necessary for the Service to function:

| Processor | Purpose | Data shared |
|---|---|---|
| **Google LLC** | OAuth sign-in (only if you connect a Google account) | Your sign-in request and the OAuth tokens needed to verify it |
| **Anthropic, PBC** | AI campaign-brief assistant (only if you use it) | The brief text you write and a system prompt; no account identifiers |
| **DigitalOcean LLC** | Application hosting (Frankfurt region, EU) | All data hosted on the Service runs on DigitalOcean infrastructure |
| **Resend, Inc.** | Transactional emails (account, digest summaries, password resets) | Recipient email address, recipient name, and email body |

We do not share data with advertising networks, data brokers, or analytics providers other than what is strictly necessary to operate the Service.

## 5. International transfers

Our application servers and database are located in the European Union (DigitalOcean Frankfurt region). Some processors named above (Google, Anthropic, Resend) may process data in the United States. Where personal data is transferred outside the EEA, we rely on the recipient's GDPR-compliant transfer mechanisms (Standard Contractual Clauses or equivalent).

## 6. Retention

- **Account data** is retained while your account is active.
- **Activity logs** are retained for the operational lifetime of the relevant campaign and for a reasonable audit window thereafter.
- **Database backups** are retained on a rolling basis (typically up to 30 days) for disaster recovery.
- **Session cookies** are deleted when you log out or when the session expires.

When you ask us to delete your account, we delete or irreversibly anonymise your personal data within a reasonable period, except where we are required by law or by legitimate business need (such as ongoing audit or dispute resolution) to retain it.

## 7. Security

We implement reasonable technical and organisational measures to protect your data, including:

- HTTPS/TLS for all traffic.
- Salted-hash storage of passwords (never plaintext).
- Two-factor authentication (TOTP or Google) required for all accounts.
- Role-based access control inside the application.
- Regular system updates and database backups.

No system is perfectly secure. If we become aware of a personal-data breach affecting you, we will notify you and the appropriate supervisory authority as required by applicable law.

## 8. Your rights

Subject to applicable law (Israeli Privacy Protection Law, EU GDPR, and equivalent regimes), you have the right to:

- Access the personal data we hold about you.
- Correct inaccurate or incomplete data.
- Request deletion of your data.
- Receive a copy of your data in a portable format.
- Withdraw consent (for example, by disconnecting your Google account).
- Object to certain processing.
- Lodge a complaint with a supervisory authority — in Israel, the Privacy Protection Authority (PPA); in the EU, the supervisory authority of your country of residence.

To exercise any of these rights, email **support@erate.co.il**. Because accounts are administered by your organisation, certain requests may need to be coordinated with your organisation's MadData administrator.

## 9. Cookies

We use a minimal set of cookies:

- A session cookie that keeps you authenticated. It is encrypted, marked HttpOnly and Secure, and expires when your session ends.
- A CSRF protection token. Required for the security of authenticated requests.

We do not use third-party advertising cookies, tracking pixels, or analytics cookies.

## 10. Children

The Service is not directed to children under the age of 16, and we do not knowingly collect data from anyone under that age. If you become aware that a child has provided us with personal data, contact us and we will delete it.

## 11. Changes to this policy

We may update this policy from time to time. Material changes will be notified to your account email a reasonable period before they take effect. The "Last updated" date at the top of this policy reflects the most recent revision.

## 12. Contact

For any privacy-related question or request, contact:

**eRate online measurement solutions ltd**
Email: **support@erate.co.il**

## 13. Governing law

This policy is governed by the laws of the State of Israel. Disputes shall be subject to the exclusive jurisdiction of the competent courts of Tel Aviv–Jaffa, except where local law grants you the right to bring a claim before the courts of your country of residence.
