TAILORPRO - XAMPP STARTER

1. Copy the "tailorpro" folder into:
   C:\xampp\htdocs\

2. Start Apache and MySQL in XAMPP.

3. Open:
   http://localhost/phpmyadmin

4. Import:
   database/tailorpro.sql

5. Open:
   http://localhost/tailorpro/

6. Create a tailor account.

Current working features:
- Database schema for multi-tailor system
- Tailor registration
- Secure password hashing
- Login/logout
- Session authentication
- Basic dashboard
- Customer/order/payment/measurement database structure

Next implementation modules:
- Customer CRUD
- Customer measurement management
- Order CRUD
- Payment history
- Automatic balances
- Collection reminders
- Tailor profile/branding
- Reports
- Receipts
- Responsive dashboard improvements

IMPORTANT:
If your XAMPP MySQL uses a password for root, edit:
config/config.php
and change $pass.
