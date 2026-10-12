<?php

return [
    'label' => 'Client',
    'plural' => 'Clients',
    'no_owner' => 'No owner yet',
    'per_interval' => 'per :interval',
    'no_features' => 'No extra features',

    'tabs' => [
        'all' => 'All',
    ],

    'card' => [
        'plan' => 'Plan',
        'branches' => 'Branches',
        'team' => 'Team',
        'trial_left' => '{0} Trial ends today|{1} 1 trial day left|[2,*] :count trial days left',
        'past_due' => 'Payment overdue',
        'since' => 'Client since :date',
        'mo' => 'mo',
        'yr' => 'yr',
    ],

    'empty' => [
        'heading' => 'No clients yet',
        'description' => 'Open your first restaurant account, or convert a lead from the website.',
    ],

    'sections' => [
        'identity' => 'Brand',
        'billing_details' => 'Contact & billing details',
        'billing_details_hint' => 'Shown on invoices.',
        'features' => 'Features',
        'features_hint' => 'Follow the plan, or switch a feature on or off for this client only.',
        'internal' => 'Internal notes',
        'internal_hint' => 'Only the platform team sees these.',
        'subscription' => 'Subscription',
        'owner' => 'Owner',
    ],

    'features' => [
        'default' => 'Plan',
        'on' => 'On',
        'off' => 'Off',
    ],

    'fields' => [
        'name' => 'Restaurant name',
        'slug' => 'Link',
        'slug_hint' => 'Used in the restaurant’s links. Letters, numbers and dashes.',
        'accent_color' => 'Brand color',
        'accent_color_hint' => 'Used on their cards and, later, their ordering app.',
        'default_locale' => 'Main language',
        'timezone' => 'Time zone',
        'legal_name' => 'Legal name',
        'tax_number' => 'Tax number',
        'contact_email' => 'Contact email',
        'contact_phone' => 'Contact phone',
        'billing_address' => 'Billing address',
        'status' => 'Status',
        'plan' => 'Plan',
        'billing_status' => 'Billing',
        'branches' => 'Billed branches',
        'period_amount' => 'Amount',
        'trial_ends' => 'Trial ends',
        'next_invoice' => 'Next invoice',
        'grace_ends' => 'Suspends on',
        'suspension_reason' => 'Reason',
        'owner_name' => 'Owner’s name',
        'owner_email' => 'Owner’s email',
        'owner_phone' => 'Owner’s mobile',
        'owner_state' => 'Access',
        'send_invitation' => 'Email the invitation',
        'send_invitation_hint' => 'You can also share the link by WhatsApp after creating the account.',
        'negotiated_price' => 'Negotiated price',
        'unit_price' => 'Price per branch',
        'unit_price_hint' => 'Overrides the plan price for this client.',
        'workspace_link' => 'Workspace',
        'client_since' => 'Client since',
    ],

    'owner_state' => [
        'active' => 'Signed in :when',
        'pending' => 'Invitation pending',
    ],

    'suspension' => [
        'billing' => 'Unpaid invoice past the grace period',
    ],

    'wizard' => [
        'title' => 'New client',
        'steps' => [
            'brand' => 'Restaurant',
            'brand_hint' => 'Name, link and language',
            'owner' => 'Owner',
            'owner_hint' => 'Who runs the account',
            'plan' => 'Plan',
            'plan_hint' => 'Subscription and trial',
            'review' => 'Review',
            'review_hint' => 'Confirm and open',
        ],
        'existing_owner_hint' => 'This person already has an account: the restaurant will be added to it.',
        'new_owner_hint' => 'They’ll receive an invitation to choose their own password.',
        'branch_limit' => 'This plan allows up to :count branches.',
        'review' => 'You are opening :name for :owner (:email) on the :plan plan with :branches billed branch(es).',
        'review_trial' => '{1} It starts with a one-day free trial.|[2,*] It starts with a :count-day free trial.',
        'review_no_trial' => 'There is no trial: the first invoice is issued right away.',
        'review_invite' => 'The owner will receive their invitation by email.',
        'review_no_invite' => 'No email is sent: share the invitation link yourself.',
        'created' => 'Client account opened',
        'created_invited' => 'The owner’s invitation is ready to share.',
        'created_existing_owner' => 'The restaurant was added to the owner’s existing account.',
    ],

    'actions' => [
        'new' => 'New client',
        'more' => 'More',
        'change_plan' => 'Change plan',
        'change_plan_description' => 'New terms apply from the next invoice.',
        'issue_invoice' => 'Issue invoice now',
        'issue_invoice_description' => 'Invoices the current period (:amount). If it was already invoiced, the existing invoice is kept.',
        'suspend' => 'Suspend',
        'suspend_heading' => 'Suspend :restaurant?',
        'suspend_description' => 'Guests can’t order and only the owner can sign in until you reactivate the account.',
        'reactivate' => 'Reactivate',
        'reactivate_heading' => 'Reactivate :restaurant?',
        'reactivate_description' => 'Ordering and team access come back immediately.',
        'resend_invitation' => 'Resend invitation',
        'resend_invitation_description' => 'A new link is emailed to :email. Earlier links stop working.',
        'share_whatsapp' => 'Send by WhatsApp',
    ],

    'notifications' => [
        'plan_changed' => 'Plan changed to :plan',
        'invoice_ready' => 'Invoice :number is ready',
        'suspended' => 'Client suspended',
        'reactivated' => 'Client reactivated',
        'invitation_ready' => 'Invitation link ready',
        'invitation_ready_body' => ':url · valid for 7 days',
        'whatsapp_message' => 'Welcome to Drive-Thru Plus! Your :restaurant workspace is ready. Choose your password here: :url',
    ],

    'impersonation' => [
        'action' => 'View as owner',
        'heading' => 'Open :restaurant as its owner',
        'description' => 'You’ll see the workspace exactly as the owner does. The visit and its reason are recorded, and their personal security settings stay locked.',
        'reason' => 'Reason',
        'reason_placeholder' => 'e.g. Helping set up the menu (ticket #241)',
        'submit' => 'Open workspace',
        'banner' => 'You are viewing as :user. Platform account: :admin',
        'leave' => 'Return to platform',
        'blocked' => 'Personal security settings aren’t available while viewing as the owner.',
        'errors' => [
            'already_active' => 'Return to the platform first.',
            'no_owner' => 'This restaurant has no active owner to view as.',
            'reason' => 'Please give a short reason (at least 5 characters).',
        ],
    ],

    'notices' => [
        'dismiss' => 'Dismiss',
        'suspended' => [
            'title' => 'Your account is paused',
            'body' => 'Online ordering is off and only you can sign in. Visit Billing to settle any due invoice, or contact us.',
        ],
    ],

    'team' => [
        'title' => 'Team',
        'name' => 'Name',
        'position' => 'Position',
        'last_seen' => 'Last seen',
        'never' => 'Never',
        'joined' => 'Joined',
        'empty' => 'No team members yet',
    ],

    'errors' => [
        'owner_is_platform_staff' => 'This email belongs to the platform team and can’t own a restaurant.',
    ],
];
