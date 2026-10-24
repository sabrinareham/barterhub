# Barter Hub - Front-end (Step 1 of 3)

HTML5 + CSS3 + JavaScript + Bootstrap 5. No PHP and no database yet.

## How to open
Unzip, then double-click `index.html`. You need internet once per session because
Bootstrap and Bootstrap Icons load from a CDN (jsDelivr).

## Demo login
- User: any email + password with 6+ characters (`auth/login.php`)
- Admin: `auth/login.php` (any email + 6+ characters)

## Folder map (matches the backend folder plan)
- `index.html, browse.php, item.html, about.html, contact.html, terms.html` - public pages (guest)
- `auth/` - login, register
- `user/` - dashboard, browse, item, add-item, my-items, matches, requests, chat, rate, profile, premium
- `admin/` - login, dashboard, activity, users, items, submissions, exchanges, chats, searches, complaints, categories, payments, add-listing
- `assets/css/style.css` - all custom styling
- `assets/js/app.js` - demo behaviour (filters, validation, request buttons, chat, ratings)
- `assets/js/data.js` - sample items (will come from MySQL later)

## What is fake in this step
Nothing is stored on a server. Buttons only change what you see on the page.
The only thing remembered is a newly added item (kept in your browser) so it shows in My items and Latest items.

## Item approval flow (admin decides what is public)
1. User fills `user/add-item.php` -> item is saved as **pending**. Only that user sees it (My items: "Waiting for admin").
2. Admin opens `admin/submissions.php` -> **Approve** (goes public) or **Reject** (with a reason the user can read).
3. Only **approved** items appear on the home page, Browse and the dashboard for everyone.

To test this in one browser: add an item as a user, open the admin panel in the same browser and approve it.
(The demo uses your browser's storage to share data between the user and admin pages; the real system will use MySQL.)

## Exchange request flow
Item page -> Request exchange -> appears in the sender's **Sent** tab.
The receiver sees it under **Received** and can Accept, Reject, or send a Counter offer.
