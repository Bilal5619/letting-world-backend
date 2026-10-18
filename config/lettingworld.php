<?php

return [

    /*
     * Certificate / compliance types. `months` is the default validity used to work out the
     * expiry date when only the issue date is entered (null = no fixed expiry).
     * `required` decides whether a property is flagged as missing it:
     *   true = every property, 'gas' = only properties with a gas supply.
     */
    'certificate_types' => [
        'gas_safety' => ['label' => 'Gas Safety Certificate (CP12)', 'months' => 12, 'required' => 'gas'],
        'eicr' => ['label' => 'EICR (Electrical)', 'months' => 60, 'required' => true],
        'epc' => ['label' => 'Energy Performance Certificate', 'months' => 120, 'required' => true],
        'boiler_service' => ['label' => 'Boiler Service', 'months' => 12, 'required' => false],
        'pat' => ['label' => 'PAT Testing', 'months' => 12, 'required' => false],
        'legionella' => ['label' => 'Legionella Risk Assessment', 'months' => 24, 'required' => false],
        'fire_risk' => ['label' => 'Fire Risk Assessment', 'months' => 12, 'required' => false],
        'smoke_co' => ['label' => 'Smoke & CO Alarm Check', 'months' => 12, 'required' => false],
        'hmo_licence' => ['label' => 'HMO / Selective Licence', 'months' => 60, 'required' => false],
        'landlord_insurance' => ['label' => 'Landlord Insurance', 'months' => 12, 'required' => false],
        'deposit_protection' => ['label' => 'Deposit Protection Certificate', 'months' => null, 'required' => false],
        'inventory' => ['label' => 'Inventory Report', 'months' => null, 'required' => false],
        'other' => ['label' => 'Other certificate', 'months' => null, 'required' => false],
    ],

    /** Certificates due within this many days are shown as "expiring soon" (amber). */
    'expiring_soon_days' => 60,

    /** Automatic expiry alerts: days before expiry, then a reminder every N days once expired. */
    'alert_days' => [60, 30, 14, 7],
    'expired_reminder_every_days' => 7,

    /** Landlords are told this many days before a tenancy end date. */
    'tenancy_end_alert_days' => [60, 30],

    /*
     * Which 11Done service fulfils each certificate renewal (11Done service IDs).
     * Certificate types not listed here can't be booked through 11Done.
     * TO CONFIRM with 11Done (Gas Safety: 60 "Landlord Gas safety inspection" or 75 "CP12").
     */
    'elevendone_services' => [
        'gas_safety' => (int) env('ELEVENDONE_SERVICE_GAS_SAFETY', 60),
        'eicr' => (int) env('ELEVENDONE_SERVICE_EICR', 59),
        'epc' => (int) env('ELEVENDONE_SERVICE_EPC', 78),
        'pat' => (int) env('ELEVENDONE_SERVICE_PAT', 76),
        'boiler_service' => (int) env('ELEVENDONE_SERVICE_BOILER', 74),
    ],

    /*
     * Prices for renewals the Letting World team arranges itself (services 11Done doesn't
     * offer, or everything when 11Done isn't connected). 11Done services are always priced
     * by 11Done for the property's postcode.
     */
    'service_prices' => [
        'gas_safety' => 69.00,
        'eicr' => 149.00,
        'epc' => 79.00,
        'boiler_service' => 85.00,
        'pat' => 79.00,
        'legionella' => 89.00,
        'fire_risk' => 149.00,
        'smoke_co' => 59.00,
    ],

    /** Times are stored in UTC and shown to the Letting World team in UK time. */
    'display_timezone' => 'Europe/London',

    /** Earliest appointment for team-arranged renewals: today + this many days. */
    'manual_min_days' => (int) env('LW_MANUAL_MIN_DAYS', 2),

    'document_categories' => [
        'tenancy_agreement' => 'Tenancy agreement',
        'deposit' => 'Deposit documents',
        'inventory' => 'Inventory',
        'gas_safety' => 'Gas Safety Certificate',
        'eicr' => 'EICR',
        'epc' => 'EPC',
        'pat' => 'PAT',
        'insurance' => 'Insurance',
        'inspection' => 'Inspection report',
        'maintenance' => 'Maintenance document',
        'id_document' => 'ID document',
        'right_to_rent' => 'Right to Rent',
        'reference' => 'Reference',
        'invoice' => 'Invoice / receipt',
        'other' => 'Other',
    ],

    'maintenance_categories' => [
        'plumbing' => 'Plumbing / leak',
        'heating' => 'Heating / boiler',
        'electrical' => 'Electrical',
        'appliance' => 'Appliance',
        'damp' => 'Damp / mould',
        'structural' => 'Doors, windows & structure',
        'pest' => 'Pests',
        'security' => 'Locks & security',
        'other' => 'Other',
    ],

    /** Accepted upload types and maximum size in KB. */
    'upload_mimes' => 'pdf,jpg,jpeg,png,webp,heic,heif,doc,docx',
    'upload_max_kb' => 10240,

    /** Minutes a signed file link stays valid. */
    'file_link_minutes' => 30,
];
