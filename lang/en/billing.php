<?php

return [
    'custom_pricing' => 'Custom pricing',
    'overdue' => 'Overdue',
    'overdue_only' => 'Overdue only',

    'interval' => [
        'month' => 'month',
        'year' => 'year',
    ],

    'subscription_status' => [
        'trialing' => 'Free trial',
        'active' => 'Active',
        'past_due' => 'Payment overdue',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
    ],

    'invoice_status' => [
        'open' => 'Due',
        'paid' => 'Paid',
        'void' => 'Void',
    ],

    'features' => [
        'loyalty' => ['label' => 'Loyalty & rewards', 'description' => 'Points, tiers and a rewards wallet for regulars.'],
        'promotions' => ['label' => 'Promotions & coupons', 'description' => 'Discounts, happy hours, first-order offers and codes.'],
        'campaigns' => ['label' => 'Customer campaigns', 'description' => 'WhatsApp, SMS and push messages to customer segments.'],
        'arrival_tracking' => ['label' => 'Arrival tracking', 'description' => 'Live distance and automatic “I’m here” when cars arrive.'],
        'scheduled_orders' => ['label' => 'Scheduled orders', 'description' => 'Order ahead for a chosen pickup time.'],
        'kitchen_stations' => ['label' => 'Kitchen stations', 'description' => 'Route items to grill, fryer and drinks screens.'],
        'advanced_reports' => ['label' => 'Advanced reports', 'description' => 'Finance, kitchen speed and scheduled report emails.'],
        'pos_integration' => ['label' => 'POS integration', 'description' => 'Send orders to the restaurant’s point of sale.'],
        'api_access' => ['label' => 'API & webhooks', 'description' => 'Connect other systems to orders and menus.'],
        'ai_assistant' => ['label' => 'AI assistant', 'description' => 'Smart recommendations, search and menu writing help.'],
        'custom_domain' => ['label' => 'Custom domain', 'description' => 'Ordering on the restaurant’s own web address.'],
        'priority_support' => ['label' => 'Priority support', 'description' => 'A dedicated account manager and faster answers.'],
    ],

    'invoices' => [
        'label' => 'Invoice',
        'plural' => 'Invoices',
        'empty' => 'No invoices yet',
    ],

    'invoice' => [
        'line' => ':plan plan · per branch',
    ],

    'fields' => [
        'number' => 'Number',
        'period' => 'Period',
        'total' => 'Total',
        'due' => 'Due',
        'issued' => 'Issued',
        'paid_at' => 'Paid on',
        'payment_method' => 'Payment method',
        'payment_reference' => 'Reference',
        'void_reason' => 'Why is it cancelled?',
    ],

    'payment_methods' => [
        'bank_transfer' => 'Bank transfer',
        'card' => 'Card',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ],

    'actions' => [
        'download' => 'Download PDF',
        'mark_paid' => 'Record payment',
        'mark_paid_heading' => 'Record payment for :number',
        'mark_paid_description' => 'Amount due: :total. The client is reactivated automatically if this was the last overdue invoice.',
        'void' => 'Void',
        'void_heading' => 'Void :number?',
        'void_description' => 'The invoice stays in the records with its number but is no longer due.',
    ],

    'notifications' => [
        'paid' => 'Payment recorded for :number',
        'voided' => ':number was voided',
    ],

    'errors' => [
        'invoice_not_open' => 'Only invoices that are still due can be changed.',
    ],

    'owner' => [
        'title' => 'Billing',
        'subheading' => 'Your plan, payments and invoices.',
        'your_plan' => 'Your plan',
        'billed' => 'Billed every',
        'trial_left' => '{0} Your free trial ends today|{1} One day left in your free trial|[2,*] :count days left in your free trial',
        'how_to_pay' => 'Payments',
        'due' => ':number is due',
        'overdue' => ':number is overdue',
        'all_paid' => 'You’re all paid up. Thank you!',
        'questions' => 'Questions about billing? Write to',
        'no_plan' => 'No active plan yet. Our team will set it up with you.',
    ],

    'mail' => [
        'greeting' => 'Hello,',
        'salutation' => 'Warm regards, the Drive-Thru Plus team',
        'invoice_issued' => [
            'subject' => 'Invoice :number',
            'body' => 'Your invoice :number for :total is ready. It is due on :due.',
            'attachment' => 'The PDF is attached, and every invoice is also available on the Billing page of your workspace.',
        ],
        'overdue' => [
            'subject' => 'Payment overdue: :number',
            'body' => 'Invoice :number (:total) is past its due date. Please settle it before :date to keep your drive-thru taking orders.',
        ],
        'suspended' => [
            'subject' => ':restaurant is paused',
            'body' => 'Online ordering is paused for now. You can still sign in as the owner to see the details and settle billing.',
            'action' => 'Open your workspace',
        ],
        'reactivated' => [
            'subject' => ':restaurant is live again',
            'body' => 'Welcome back! Customers can order again and your team has full access.',
            'action' => 'Open your workspace',
        ],
    ],
    'limits' => [
        'branches' => [
            'reached' => '{1} Your :plan plan includes 1 branch. Upgrade to add more.|[2,*] Your :plan plan includes :count branches. Upgrade to add more.',
            'usage' => ':used of :limit branches used',
        ],
        'employees' => [
            'reached' => '{1} Your :plan plan includes 1 team member. Upgrade to add more.|[2,*] Your :plan plan includes :count team members. Upgrade to add more.',
            'usage' => ':used of :limit team members',
        ],
        'unlimited' => 'Unlimited on your plan',
        'upgrade' => 'Talk to us about upgrading',
    ], ];
