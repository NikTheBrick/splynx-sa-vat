# splynx-sa-vat

Before start:
1. Backup a DB
2. Backup all Pending services, because we need to create them manually
3. Backup all additional fields values (for all service types) - need to be restored for old and new services (for new only if they are not unique!!!)

For the script need to:
1. Create API key
2. Permissions:
    - Tariff plans:
        a. Internet - Select all
        b. Voice - Select all
        c. One-time - Select all
        d. Recurring - Select all
        e. Change plan - Select all

    - Customers:
        a. Customer Internet services - Select all
        b. Customer Voice Services - Select all
        c. Customer recurring services - Select all

3. API Domain URL
4. TAX ID  (could already exist or need to add it manually)


Finally, run the script by command:
php run.php
