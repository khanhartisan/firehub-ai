# 9 Types of QR Codes: Which One Do You Actually Need?

Learn about 9 types of QR codes including URL, vCard, WiFi, and more. See what each type does, when to use it, and whether you need static or dynamic.


## Types of QR Codes

There are nine common types of QR codes, each encoding a different kind of data: URL, plain text, vCard, WiFi, email, SMS, phone, location, and app store. The type you choose determines what happens when someone scans the code, from opening a web page to connecting to a WiFi network to saving contact details directly to their phone.

Most people just need a URL code. According to [Bitly's 2024 QR Code Trends report](https://bitly.com/), URL codes account for over 75% of all QR codes created on their platform. But picking the right type matters, because a QR code that opens a web page behaves very differently from one that connects someone to your WiFi network. The wrong choice means the scan works, but the result isn't what you intended.

This guide covers all nine types defined in the [QR code specification (ISO/IEC 18004)](https://www.iso.org/standard/62021.html), what each one does, when to use it, and whether you need a static or dynamic version.

Every QR code looks the same on the surface: a grid of black and white squares. The difference is what's encoded inside. A URL code opens a web page. A WiFi code connects you to a network. A vCard code saves someone's contact info. Nine data formats, nine different outcomes from the same visual pattern.

### Key Takeaways:
- URL codes are the most common type, accounting for over 75% of QR codes created ([Bitly, 2024](https://bitly.com/)).
- vCard, WiFi, email, SMS, and phone codes trigger specific device actions without opening a browser.
- The QR code market reached [$13 billion in 2025](https://wavecnct.com/blogs/news/qr-code-statistics), with dynamic codes representing 64.92% of revenue (Mordor Intelligence, 2025).
- Most types can be created as either static or dynamic. Dynamic gives you the ability to update the destination and track scans.
- The "right" type depends on what you want to happen when someone scans the code.

## The 9 Types of QR Codes at a Glance

| Type | What it does | Best for | Static option | Dynamic option |
|------|--------------|----------|---------------|----------------|
| URL | Opens a web page | Websites, landing pages, menus | Yes | Yes |
| Plain text | Displays text on screen | Short messages, serial numbers | Yes | No |
| vCard | Saves contact details to phone | Business cards, name badges | Yes | Yes |
| WiFi | Connects to a WiFi network | Guest networks, offices, cafes | Yes | No |
| Email | Opens a pre-filled email draft | Customer support, feedback | Yes | Yes |
| SMS | Opens a pre-filled text message | Opt-ins, quick replies | Yes | Yes |
| Phone | Dials a phone number | Emergency contacts, support lines | Yes | Yes |
| Location | Opens a map to specific coordinates | Store locations, event venues | Yes | Yes |
| App Store | Opens an app listing for download | App promotion, onboarding | Yes | Yes |

### URL QR Codes
![Smartphone scanning a URL QR code that opens a restaurant menu website](https://freeqr.com/storage/posts/01KMTBSWK9ECHATG9RFHBNW9CY.jpg)

A **URL QR code** encodes a web address that opens in the scanner's default browser. It is the most common type, accounting for over 75% of all QR codes created ([Bitly, 2024](https://bitly.com/)). The code encodes a full URL (like `https://example.com/menu`) and the phone treats it as a clickable link. URL QR codes are the backbone of the [$13 billion global QR code market](https://wavecnct.com/blogs/news/qr-code-statistics) (Mordor Intelligence, 2025).

**When to use it:** Linking to any web content. Product pages, restaurant menus, event registration forms, portfolios, social media profiles, or landing pages.

**Static vs. dynamic:** A static URL code bakes the URL directly into the pattern. It works forever, but the destination can never be changed. A dynamic URL code routes through a redirect server, so you can update where the code points without reprinting. If you're printing codes on physical materials and might need to change the link later, dynamic is the safer choice.

**Real-world example:** A cafe prints a QR code on table tents that links to their online menu. With a dynamic URL code, they update the menu link every season without replacing the table tents.

## vCard QR Codes
![Person tapping phone after scanning a vCard QR code from a printed business card](https://freeqr.com/storage/posts/01KMTBSWK9ECHATG9RFHBNW9CY.jpg)

A **vCard QR code** saves contact details directly to the scanner's phone. One scan, and your name, phone number, email, company, job title, and website appear as a new contact ready to save. No typing required. The vCard format follows the [vCard 4.0 standard (RFC 6350)](https://datatracker.ietf.org/doc/html/rfc6350) and supports up to 30 contact fields including address, URL, and photo. Most QR code generators use vCard 3.0 ([RFC 2426](https://datatracker.ietf.org/doc/html/rfc2426)) for broader device compatibility.

**When to use it:** Business cards, conference badges, email signatures, company directories, and networking events. Anywhere someone might want to save your contact information.

**Static vs. dynamic:** A static vCard code encodes all contact details into the pattern itself. This makes the code denser (more data means more squares), which can make it harder to scan at small sizes. A dynamic vCard code stores only a short redirect URL in the pattern and keeps the contact details on a server. This produces a simpler, easier-to-scan code and lets you update your details (new phone number, new job title) without reprinting.

**Real-world example:** A real estate agent prints a vCard QR code on their business card. When they change brokerages six months later, they update the contact details through their QR platform. Every card already in circulation now shares the updated information.

### WiFi QR Codes
A **WiFi QR code** connects the scanner's phone to a wireless network automatically. No need to ask for the password, spell it out, or type it character by character. The code encodes the network name (SSID), password, and encryption type (WPA, WPA2, or WEP). The phone reads it and offers to join the network. WiFi QR codes are supported natively on iOS 11+ and Android 10+, covering [over 98% of active smartphones](https://gs.statcounter.com/) worldwide.

**When to use it:** Guest WiFi in offices, hotels, cafes, Airbnbs, co-working spaces, and events. Anywhere you want people to connect without sharing the password verbally or on a printed sign.

**Static vs. dynamic:** WiFi codes are typically static. The network credentials are encoded directly into the pattern. If you change your WiFi password, you need to generate a new code. Some generators offer dynamic WiFi codes, but since the phone needs the actual credentials to connect (not a URL redirect), the practical benefit of dynamic is limited here.

**Real-world example:** A coworking space posts a framed QR code at the front desk. New members scan it to connect to WiFi on their first visit. When the space changes its password quarterly, they print a new code.

### Email QR Codes
An email QR code opens the scanner's default email app with a pre-filled recipient address, subject line, and optional body text. The user just hits send.

**When to use it:** Customer feedback collection, support requests, RSVP confirmations, and survey participation. Useful when you want someone to send a specific email without typing the address or subject line themselves.

**Static vs. dynamic:** A static email code encodes the `mailto:` link directly. A dynamic version lets you update the recipient address or subject line after printing, which is useful if your support email changes or you want to rotate subject lines for tracking.

**Real-world example:** A hotel places a QR code on the bedside table. Guests scan it to send a pre-filled email to housekeeping requesting extra towels or pillows. The subject line includes the room number.

### SMS QR Codes
An SMS QR code opens the scanner's messaging app with a pre-filled phone number and message. One scan, one tap to send.

**When to use it:** Marketing opt-ins, quick customer responses, event RSVP by text, or two-factor authentication setup.

**Static vs. dynamic:** A static SMS code encodes the phone number and message directly. A dynamic version allows you to change the phone number or message text after printing, which matters if you switch messaging providers or update your opt-in keywords.

**Real-world example:** A retail store prints a QR code on receipts. Scanning it opens a text message pre-filled with "FEEDBACK" sent to their review collection number. Customers tap send and get a link to a survey by reply.

### Phone QR Codes
A phone QR code dials a phone number automatically when scanned.

**When to use it:** Support lines, emergency contacts.

**Static vs. dynamic:** Phone codes are static; the number is embedded directly in the code.

**Real-world example:** Emergency services print QR codes on their vehicles. Scanning it automatically dials the emergency hotline.