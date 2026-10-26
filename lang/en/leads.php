<?php

return [
    'label' => 'Lead',
    'plural' => 'Leads',
    'unassigned' => 'Unassigned',
    'no_notes' => 'No notes yet. Every status change is noted here automatically.',
    'whatsapp_greeting' => 'Hello :name, thank you for your interest in Drive-Thru Plus! When is a good time for a quick call?',

    'status' => [
        'new' => 'New',
        'contacted' => 'Contacted',
        'demo_booked' => 'Demo booked',
        'won' => 'Won',
        'lost' => 'Lost',
    ],

    'source' => [
        'website' => 'Website',
        'manual' => 'Added by team',
        'referral' => 'Referral',
        'phone' => 'Phone call',
        'event' => 'Event',
    ],

    'tabs' => [
        'open' => 'In progress',
        'all' => 'All',
    ],

    'sections' => [
        'request' => 'Request',
        'pipeline' => 'Pipeline',
        'notes' => 'Notes & history',
    ],

    'fields' => [
        'services' => 'Services',
        'restaurant_name' => 'Restaurant',
        'contact_name' => 'Contact person',
        'contact' => 'Contact',
        'email' => 'Email',
        'phone' => 'Mobile',
        'branches_count' => 'Branches',
        'plan' => 'Interested in',
        'message' => 'Message',
        'source' => 'Source',
        'locale' => 'Language',
        'assignee' => 'Owner',
        'follow_up_at' => 'Follow up',
        'lost_reason' => 'Why it was lost',
        'note' => 'Note',
        'received' => 'Received',
        'contacted_at' => 'First contacted',
        'converted_to' => 'Became client',
    ],

    'filters' => [
        'mine' => 'Assigned to me',
        'follow_up_due' => 'Follow-up due',
    ],

    'actions' => [
        'new' => 'New lead',
        'change_status' => 'Update status',
        'add_note' => 'Add note',
        'assign_to_me' => 'Assign to me',
        'whatsapp' => 'WhatsApp',
        'convert' => 'Convert to client',
        'convert_heading' => 'Open :restaurant as a client',
        'convert_description' => 'Check the details: this opens the restaurant, its owner and the subscription in one step, and closes the lead as won.',
        'convert_submit' => 'Open client account',
    ],

    'notifications' => [
        'status_updated' => 'Status set to :status',
        'note_added' => 'Note added',
        'assigned' => 'Lead assigned to you',
        'converted' => ':restaurant is now a client',
        'new' => [
            'title' => 'New request: :restaurant',
            'body' => ':contact · :branches branch(es)',
            'action' => 'Open lead',
        ],
    ],

    'notes' => [
        'status_changed' => 'Status: :from → :to',
        'converted' => 'Converted into the client :restaurant',
    ],

    'errors' => [
        'won_needs_conversion' => 'Use “Convert to client” to win a lead.',
        'already_won' => 'This lead is already a client.',
        'lost_needs_reason' => 'Please say why the lead was lost.',
    ],

    'empty' => [
        'heading' => 'No leads here',
        'description' => 'Requests from the website appear here instantly.',
    ],

    'mail' => [
        'ack' => [
            'subject' => 'We received your request',
            'greeting' => 'Hello :name,',
            'body' => 'Thank you for your interest in bringing :restaurant online with Drive-Thru Plus.',
            'next' => 'A member of our team will contact you within one business day to arrange a short demo.',
        ],
    ],

    'form' => [
        'eyebrow' => 'Book a demo',
        'title' => 'Book your free demo.',
        'lead' => 'Tell us a little about your restaurant. We’ll set everything up with you and have you taking orders within days.',
        'optional' => 'optional',
        'plan_help' => 'Not sure yet',
        'message_placeholder' => 'Anything we should know? Opening hours, number of lanes, current POS…',
        'privacy' => 'We only use these details to contact you about Drive-Thru Plus.',
        'submit' => 'Send request',
        'success_title' => 'Thank you!',
        'success_text' => 'Your request is in. We’ll contact you within one business day.',
        'steps' => [
            'one' => ['title' => 'Send your request', 'text' => 'Two minutes, no commitment.'],
            'two' => ['title' => 'We set you up', 'text' => 'Your account, branches and team, configured with you.'],
            'three' => ['title' => 'Start taking orders', 'text' => 'Upload your menu, print your QR and go live.'],
        ],
        'attributes' => [
            'restaurant_name' => 'Restaurant name',
            'contact_name' => 'Your name',
            'email' => 'Email',
            'phone' => 'Mobile',
            'branches_count' => 'Number of branches',
            'plan' => 'Plan',
            'message' => 'Message',
        ],
    ],
];
