<?php

return [
    'title' => 'Guide',
    'navigation' => 'Guide',

    'sections' => [
        'operations' => ['title' => 'Operations', 'summary' => 'Today at a glance, every branch live, ratings and guest problems.'],
        'menu' => ['title' => 'Menu', 'summary' => 'Items, sections, options, schedules and how the menu looks.'],
        'growth' => ['title' => 'Growth', 'summary' => 'Guests, points, rewards, offers and messages that bring guests back.'],
        'branches' => ['title' => 'Branches', 'summary' => 'Locations, opening hours, screens and the links guests order from.'],
        'team' => ['title' => 'Team', 'summary' => 'Who works where and what each person may do.'],
        'restaurant' => ['title' => 'Restaurant', 'summary' => 'Brand, plan and invoices, connections and your own account.'],
        'screens' => ['title' => 'Branch screens', 'summary' => 'The full-screen displays your team uses in the kitchen, at the window and on the TV.'],
        'guests' => ['title' => 'What guests see', 'summary' => 'The pages guests use on their phones, so you know what they experience.'],
    ],

    'start' => [
        'title' => 'Start here',
        'summary' => 'Seven steps from an empty workspace to your first order. Each one takes a few minutes.',
        'steps' => [
            'settings' => 'Add your name, logo and legal details.',
            'branches' => 'Add a branch, its map pin and opening hours.',
            'products' => 'Add your menu items with photos and prices.',
            'menu_design' => 'Choose how your menu looks to guests.',
            'team' => 'Invite your team and give each a position.',
            'screens' => 'Pair a kitchen screen with a 6-digit code.',
            'links' => 'Print your QR poster for the lane.',
        ],
    ],

    'ui' => [
        'subheading' => 'What every page and button does, in plain words.',
        'search' => 'Search the guide',
        'search_placeholder' => 'Try “refund” or “QR poster”',
        'search_shortcut' => 'Press / to search',
        'results' => '{0} No articles match|{1} 1 article matches|[2,*] :count articles match',
        'no_results_title' => 'Nothing matches that yet',
        'no_results_body' => 'Try a shorter word, or the name of the page you are looking for.',
        'clear' => 'Clear search',
        'step' => 'Step :number',
        'when' => 'When to use it',
        'actions' => 'On this page',
        'tips' => 'Good to know',
        'who' => 'Who can use it',
        'everyone' => 'Everyone on the team',
        'guests' => 'Your guests',
        'open' => 'Open this page',
        'not_on_plan' => 'Not on your plan',
        'see_billing' => 'See Billing',
        'help' => 'Help for this page',
        'full_guide' => 'Read the full guide',
        'articles' => '{1} 1 article|[2,*] :count articles',
    ],

    'articles' => [
        'dashboard' => [
            'title' => 'Overview',
            'summary' => 'Your restaurant at a glance: branches open now, your team, screens online and the guest rating.',
            'when' => 'Open it first every day. Until you go live, it also shows the setup checklist.',
            'actions' => [
                ['Get ready to go live', 'The setup checklist: logo, first branch, hours, team, a kitchen screen and your QR poster. Each step opens the right page.'],
                ['Branches open now', 'How many branches take orders right now, and which are paused.'],
                ['Active team', 'Team members who can sign in, and invitations still pending.'],
                ['Screens online', 'Kitchen, cashier and TV screens connected right now.'],
                ['Rating, 30 days', 'The average guest rating of the last 30 days. It opens Ratings.'],
            ],
            'tips' => [
                'The numbers stay live; there is no need to reload the page.',
                'Alerts (a low rating, a paused branch, a screen offline) appear in the bell at the top.',
            ],
        ],

        'all_branches' => [
            'title' => 'All branches (live)',
            'summary' => 'A full-screen live board of every branch: today’s totals, orders in progress and quoted waiting times.',
            'when' => 'For owners and area managers who look after several branches at once, on a laptop or a big screen.',
            'actions' => [
                ['All branches today', 'Orders and sales of every branch together.'],
                ['Branch cards', 'Each branch with its orders in progress and its quoted wait.'],
            ],
            'tips' => [
                'Only people who cover every branch (the owner and managers of all branches) can open it.',
            ],
        ],

        'ratings' => [
            'title' => 'Ratings',
            'summary' => 'What guests said after pickup: stars, the tags they picked and their comment.',
            'when' => 'Check it daily to spot a slow branch, a cold item or a great team member.',
            'actions' => [
                ['Summary', 'The average of the last 30 days, ratings per day, scores and the tags mentioned most.'],
                ['Filters', 'Show one branch, some stars only, or only ratings that have a comment.'],
                ['Guest', 'Opens the guest’s profile with their orders and earlier ratings.'],
            ],
            'tips' => [
                'Guests can rate for 48 hours after pickup, from their order page. One gentle reminder is sent if they don’t.',
                'A rating of 1 or 2 stars alerts the branch managers, so they can call the guest the same day.',
            ],
        ],

        'support' => [
            'title' => 'Support',
            'summary' => 'Problems guests report after pickup: a missing item, a wrong order, a long wait or a payment issue.',
            'when' => 'Whenever the menu badge shows a number: each one is a guest waiting for an answer.',
            'actions' => [
                ['Send reply', 'Sends your answer by WhatsApp (or SMS) and shows it on the guest’s order page.'],
                ['Refund', 'Returns all or part of the amount to the guest’s card through the payment provider.'],
                ['Give a reward', 'Puts a reward straight into the guest’s wallet. Works for cash orders too.'],
                ['Resolve', 'Closes the ticket. The guest gets a last message, with your optional closing note.'],
            ],
            'tips' => [
                'Tickets waiting over 24 hours are flagged. A quick, kind reply turns most complaints into a return visit.',
                'Only team members with the “Handle support” permission see this page.',
            ],
        ],

        'products' => [
            'title' => 'Menu items',
            'summary' => 'Every dish and drink you sell, with its photo, price, options and the branches that serve it.',
            'when' => 'When you add a new item, change a price or a photo, or stop selling something.',
            'actions' => [
                ['New item', 'Adds an item: names in Arabic and English, price, photo, section and options.'],
                ['Duplicate', 'Copies an item with all its options, handy for sizes or flavours.'],
                ['Update prices', 'Changes the price of many items at once.'],
                ['Move to section', 'Moves the selected items to another section.'],
                ['Import from Excel', 'Adds or updates many items from a spreadsheet.'],
                ['Drag to reorder', 'Sets the order items appear in their section.'],
            ],
            'tips' => [
                'Square photos look best. They are resized and sharpened for phones automatically.',
                'To hide an item at one branch for today only, use Branch menu instead.',
            ],
        ],

        'categories' => [
            'title' => 'Sections',
            'summary' => 'The groups your menu is split into, such as Burgers, Drinks and Desserts, and their order.',
            'when' => 'When you set up the menu or change what guests see first.',
            'actions' => [
                ['New section', 'Adds a section with names in Arabic and English.'],
                ['Drag to reorder', 'Sets the order sections appear on the guest menu.'],
                ['Edit', 'Renames a section or hides it from guests.'],
            ],
            'tips' => [
                'Put your best sellers in the first section: most guests order from what they see first.',
            ],
        ],

        'modifiers' => [
            'title' => 'Option groups',
            'summary' => 'Choices guests make on an item: size, extras, sauces, sugar level. One group can serve many items.',
            'when' => 'When an item needs choices, or a choice has a price.',
            'actions' => [
                ['New option group', 'Creates a group, its choices and their extra price.'],
                ['Required and limits', 'Decides whether a guest must choose and how many choices they may pick.'],
                ['Edit', 'Changes a choice’s name or price everywhere the group is used.'],
            ],
            'tips' => [
                'Create a group once (for example “Size”) and attach it to every item that needs it.',
            ],
        ],

        'schedules' => [
            'title' => 'Schedules',
            'summary' => 'Times when sections or items are offered, such as a breakfast menu until 11:00.',
            'when' => 'When part of the menu should only be orderable at certain hours or days.',
            'actions' => [
                ['New menu schedule', 'Sets the days and hours, then the sections or items it applies to.'],
                ['Edit', 'Changes the hours; guests see the change straight away.'],
            ],
            'tips' => [
                'Outside its hours an item disappears from the guest menu, so nobody orders it by mistake.',
            ],
        ],

        'menu_design' => [
            'title' => 'Menu design',
            'summary' => 'How the guest menu looks: layout, cover, colours and how items are shown.',
            'when' => 'After adding your menu, and whenever you refresh your brand.',
            'actions' => [
                ['Preview as guest', 'Opens the menu exactly as guests see it on a phone.'],
                ['Save design', 'Publishes the new look to every branch.'],
            ],
            'tips' => [
                'Your logo comes from Restaurant settings.',
            ],
        ],

        'branch_menu' => [
            'title' => 'Branch menu',
            'summary' => 'What one branch serves today: items sold out, daily stock and prices that differ at that branch.',
            'when' => 'During service, when something runs out, and when a branch charges a different price.',
            'actions' => [
                ['Out of stock today', 'Hides the item at this branch until tomorrow.'],
                ['Back in stock', 'Shows the item again straight away.'],
                ['Daily stock', 'Sets how many can be sold today; the item hides itself when they run out.'],
                ['Branch price', 'Charges a different price at this branch only.'],
            ],
            'tips' => [
                'Select several items to mark them out of stock or back in stock in one go.',
                'Branch managers can use this page without being able to change the menu itself.',
            ],
        ],

        'guests' => [
            'title' => 'Guests',
            'summary' => 'Everyone who ordered from you: their visits, spending, points, rewards, ratings and badges.',
            'when' => 'To know your regulars, answer a guest, or fix their points.',
            'actions' => [
                ['Guest profile', 'A guest’s full history: orders, ratings, problems, points and badges.'],
                ['Adjust points', 'Adds or removes points with a reason, saved in the history.'],
                ['Give a reward', 'Gives a reward to use on the next order.'],
                ['Stop offers', 'Stops campaigns to this guest when they ask you to.'],
                ['Export CSV', 'Downloads the list for your own records.'],
            ],
            'tips' => [
                'Phone numbers are visible only to team members allowed to see guest contacts.',
            ],
        ],

        'loyalty' => [
            'title' => 'Loyalty',
            'summary' => 'The rules of your points programme, tiers, earn boosts, birthday gifts and badges.',
            'when' => 'When you launch loyalty, and when you change how guests earn or what they get.',
            'actions' => [
                ['Points', 'How many points each riyal earns and when points expire.'],
                ['Tiers', 'Levels guests climb as they spend. “Start with 4 standard tiers” adds ready-made ones you can rename.'],
                ['Earn boosts', 'Extra points at certain times or on certain items.'],
                ['Birthday gift', 'A gift on each guest’s birthday, with or without a message.'],
                ['Badges', 'Weekly streaks and badges guests collect on their Rewards page.'],
                ['Adjust a guest’s points', 'Corrects one guest’s points with a reason.'],
                ['Save settings', 'Applies the rules from now on.'],
            ],
            'tips' => [
                'Nothing is on by default: guests earn points only after you set a rate and save.',
                'Changes apply to new orders; points already earned stay as they are.',
            ],
        ],

        'rewards' => [
            'title' => 'Rewards',
            'summary' => 'What guests can get for their points, such as a free drink or a discount.',
            'when' => 'When you set up loyalty and when you refresh what is on offer.',
            'actions' => [
                ['Add a reward', 'Creates a reward: the points it costs and what the guest gets.'],
                ['Edit', 'Changes the cost or the item.'],
                ['Archive', 'Stops offering it; rewards already given can still be used.'],
            ],
            'tips' => [
                'A first reward that is easy to reach brings guests back for a second visit.',
            ],
        ],

        'stamps' => [
            'title' => 'Stamp cards',
            'summary' => 'Classic “buy 9, get the 10th free” cards for one item or a section.',
            'when' => 'For simple repeat offers, such as coffee, alongside or instead of points.',
            'actions' => [
                ['Add a stamp card', 'Chooses the item, the number of stamps, the reward and a daily limit.'],
                ['Edit', 'Changes the daily limit or the reward.'],
                ['Archive', 'Stops new stamps; guests keep the rewards they already earned.'],
            ],
            'tips' => [
                'Stamps are added automatically on pickup, or by your team on the Stamp cards screen at the till.',
            ],
        ],

        'promotions' => [
            'title' => 'Promotions',
            'summary' => 'Discounts and coupon codes: a percentage off, a fixed amount, or a free item.',
            'when' => 'For a launch, a quiet afternoon, a holiday or a partner offer.',
            'actions' => [
                ['New promotion', 'Sets the discount, the dates, the branches and who may use it.'],
                ['Add a code', 'Adds a code guests type at checkout.'],
                ['Generate codes', 'Creates many single-use codes at once, for example for a partner.'],
                ['Download CSV', 'Downloads the generated codes.'],
                ['Archive', 'Ends the promotion straight away.'],
            ],
            'tips' => [
                'Discounts are always worked out by the system at checkout, so a guest can’t change them.',
            ],
        ],

        'campaigns' => [
            'title' => 'Campaigns',
            'summary' => 'Messages to a group of guests by WhatsApp, SMS or notification, with an optional reward.',
            'when' => 'To bring back guests you haven’t seen for a while, or announce something new.',
            'actions' => [
                ['New campaign', 'Chooses the guest group, writes the message and picks the channel.'],
                ['Send a test', 'Sends the message to your own phone first.'],
                ['Send / Schedule', 'Sends it straight away or at the time you choose.'],
                ['Duplicate', 'Starts a new campaign from an earlier one.'],
                ['Cancel campaign', 'Stops a scheduled campaign before it goes out.'],
            ],
            'tips' => [
                'Only guests who agreed to receive offers get campaigns. The audience count shows how many that is.',
                'After sending, the page shows how many messages arrived and how many guests came back to order.',
            ],
        ],

        'qr_codes' => [
            'title' => 'Tracked QR codes',
            'summary' => 'QR codes for flyers, posters and partners that count the scans and orders each one brings.',
            'when' => 'Whenever you print something, so you know which place or flyer works.',
            'actions' => [
                ['New QR code', 'Names the code, picks where it leads and an optional offer.'],
                ['Download', 'PNG image, SVG for designers, an A5 or A4 poster, or a 5 × 5 cm sticker.'],
                ['Switch off / Switch on', 'Stops or restarts a code without reprinting it.'],
                ['Edit', 'Changes where it leads; printed codes keep working.'],
            ],
            'tips' => [
                'Make one code per place (a car park, a mall, a partner) to compare them.',
            ],
        ],

        'referrals' => [
            'title' => 'Referrals',
            'summary' => 'Guests invite friends with their own link; both get a reward after the friend’s first picked-up order.',
            'when' => 'To grow by word of mouth from your happiest guests.',
            'actions' => [
                ['Settings', 'Chooses the reward for the guest and for the friend, and switches invites on.'],
                ['List', 'Who invited whom, and whether the reward was given or why not.'],
            ],
            'tips' => [
                'Rewards are refused automatically for a guest’s own link, existing guests, the same phone or the same car.',
            ],
        ],

        'guest_rules' => [
            'title' => 'Guest rules',
            'summary' => 'How guests are grouped every night (new, regulars, VIP, at risk…) and the limits that protect them from too many offers.',
            'when' => 'Before your first campaign, and when the groups don’t match how you see your guests.',
            'actions' => [
                ['Guest groups', 'When a guest counts as new, a regular, a VIP, a high spender, at risk or inactive.'],
                ['Offers', 'The most campaign messages per day and the quiet hours when none are sent.'],
                ['Save settings', 'Saves the rules; guests are regrouped straight away.'],
            ],
            'tips' => [
                'Groups are recalculated every night at 4 am. Campaigns use them to pick their audience.',
                'Whatever you set, a guest never gets more than one campaign a day or three a week.',
            ],
        ],

        'branches' => [
            'title' => 'Branches',
            'summary' => 'Your locations: address and map pin, opening hours, closures and whether they take orders now.',
            'when' => 'When you open a branch, change hours, close for a holiday or get too busy.',
            'actions' => [
                ['Add branch', 'Adds a location with its address, map pin and hours.'],
                ['Use my current location', 'Places the map pin where you are standing.'],
                ['Hours & closures', 'Weekly hours (split shifts included) and one-off closures such as Eid.'],
                ['Pause orders', 'Stops new orders for a while; orders in progress continue.'],
                ['Busy mode', 'Keeps taking orders but tells guests to expect a longer wait.'],
                ['Resume normal service', 'Ends a pause or busy mode.'],
                ['Switch off branch', 'Stops orders until you switch it on again, whatever its hours.'],
                ['Open guest page', 'Opens the branch menu as guests see it.'],
            ],
            'tips' => [
                'Your plan sets how many branches you can add. Billing shows how to add more.',
            ],
        ],

        'screens' => [
            'title' => 'Screens',
            'summary' => 'Tablets and TVs at your branches: kitchen, pickup window, pager counter, ready board and more.',
            'when' => 'When you set up a new device, or one is lost or replaced.',
            'actions' => [
                ['Add screen', 'Picks the branch and the screen and gives you a 6-digit pairing code.'],
                ['New pairing code', 'Creates a fresh code if the old one expired.'],
                ['Pair a replacement', 'Moves the screen to a new device; the old one disconnects once the new one pairs.'],
                ['Sign out & remove', 'Disconnects the device at once, for example if it is lost.'],
            ],
            'tips' => [
                'On the device, open the pairing address shown with the code and type the code. No password is ever typed on a shared screen.',
            ],
        ],

        'links' => [
            'title' => 'Links & QR',
            'summary' => 'Every guest link in one place: your restaurant link, each branch link and its QR poster.',
            'when' => 'When you print signs for the lane, post on social media or set up Google Maps.',
            'actions' => [
                ['Copy link', 'Copies the link to paste anywhere.'],
                ['Open', 'Opens the link to check it.'],
                ['Poster', 'Downloads a ready-to-print QR poster in Arabic and English.'],
                ['Stamp card QR', 'A code for the till: guests scan it to ask for their stamp.'],
            ],
            'tips' => [
                'Links never change, so printed codes keep working. To count scans per poster, use Tracked QR codes.',
            ],
        ],

        'team' => [
            'title' => 'Team',
            'summary' => 'Everyone who works with you: their position, branches, sign-in and PIN for the shared screens.',
            'when' => 'When someone joins, moves branch, changes role or leaves.',
            'actions' => [
                ['Invite team member', 'Sends an invitation to create their own sign-in.'],
                ['Resend invitation / Send password link', 'Sends a new one-time link, valid 7 days; any earlier link stops working.'],
                ['Set screen PIN', 'A 4 to 6 digit PIN to sign in on shared kitchen and cashier screens.'],
                ['Remove PIN', 'Removes the screen PIN.'],
                ['Suspend', 'Blocks sign-in for a while, for example during leave.'],
                ['Reactivate', 'Allows sign-in again.'],
                ['Remove from team', 'Removes their access to this restaurant.'],
            ],
            'tips' => [
                'What each person may do comes from their position. Change it in Positions.',
            ],
        ],

        'positions' => [
            'title' => 'Positions',
            'summary' => 'Roles such as Branch manager, Cashier or Kitchen, and the permissions each one has.',
            'when' => 'When you set up your team, or someone needs more or less access.',
            'actions' => [
                ['New position', 'Creates a role and ticks what it may do.'],
                ['Edit', 'Changes the permissions; everyone in that position gets them straight away.'],
                ['Restore original permissions', 'Puts a built-in position back to how it started.'],
                ['Delete', 'Removes a position nobody uses.'],
            ],
            'tips' => [
                'Each permission has a short explanation under it. As the owner, you always have them all.',
            ],
        ],

        'settings' => [
            'title' => 'Restaurant settings',
            'summary' => 'Your name, logo, brand colour, contact details, legal details, tax and receipts.',
            'when' => 'First of all, then whenever your details change.',
            'actions' => [
                ['Use on all branches', 'Applies the restaurant logo to every branch.'],
                ['Save settings', 'Saves the changes. Guests see them straight away.'],
            ],
            'tips' => [
                'Fill in the legal name and tax number before your first order: they appear on receipts and invoices.',
            ],
        ],

        'billing' => [
            'title' => 'Billing',
            'summary' => 'Your plan, the services you use, the next payment and every invoice.',
            'when' => 'To try or add a service, add branches, or download an invoice.',
            'actions' => [
                ['Try free', 'Starts a free trial of a service for the number of days shown.'],
                ['Keep it after the demo', 'Keeps the service when the trial ends; it is added to your next invoice.'],
                ['Don’t keep it', 'Lets the trial end without charge.'],
                ['Add now', 'Adds the service straight away.'],
                ['Cancel at period end', 'Stops the service when the paid period ends.'],
                ['Keep it', 'Undoes a cancellation.'],
                ['Download PDF', 'Downloads an invoice.'],
            ],
            'tips' => [
                'Services: Drive-Thru (ordering from the car), Order-Ready Call (a pager on the guest’s phone) and Rewards (stamp cards).',
            ],
        ],

        'integrations' => [
            'title' => 'Integrations',
            'summary' => 'Connections to your payment provider, kitchen printers and point-of-sale system.',
            'when' => 'When you start taking online payments or connect your POS or printers.',
            'actions' => [
                ['Test connection', 'Checks the details you entered, without charging anyone.'],
                ['Save settings', 'Saves the connection. Keys are stored encrypted.'],
            ],
            'tips' => [
                'Your provider gives you the keys. Never share them in a chat or an email.',
            ],
        ],

        'webhooks' => [
            'title' => 'Webhooks',
            'summary' => 'Sends order events to your own systems the moment they happen. For developers.',
            'when' => 'When your developer connects another system to your orders.',
            'actions' => [
                ['Add webhook', 'Sets the address and the events to send.'],
                ['Send test', 'Sends a sample event to check the address answers.'],
                ['Inspect', 'Shows what was sent and the answer received.'],
                ['Send again', 'Resends an event that failed.'],
                ['Turn off / Turn on', 'Pauses or restarts sending.'],
                ['New signing secret', 'Replaces the secret used to sign events.'],
                ['Delete', 'Stops sending to that address for good.'],
            ],
            'tips' => [
                'API documentation explains each event and how to check the signature.',
            ],
        ],

        'api_keys' => [
            'title' => 'API keys',
            'summary' => 'Keys that let your own apps read your menu and orders. For developers.',
            'when' => 'When your developer needs access to build something on top of your restaurant.',
            'actions' => [
                ['Create API key', 'Creates a key with only the access you choose.'],
                ['API documentation', 'Opens the developer documentation.'],
                ['Revoke', 'Stops a key at once.'],
            ],
            'tips' => [
                'A key is shown only once. Copy it into a safe place straight away.',
            ],
        ],

        'alerts' => [
            'title' => 'Alerts',
            'summary' => 'Which alerts reach you and how: in the workspace, by email or by WhatsApp.',
            'when' => 'When you want fewer or more alerts. It is in the menu under your name.',
            'actions' => [
                ['Alert types', 'Turns each alert on or off, such as low ratings, long waits or a paused branch.'],
                ['Save', 'Applies your choices to your own account only.'],
            ],
            'tips' => [
                'WhatsApp alerts need a mobile number on your profile.',
            ],
        ],

        'devices' => [
            'title' => 'Devices & sessions',
            'summary' => 'Every phone and computer signed in to your account.',
            'when' => 'If you lose a phone, or signed in on a shared computer. It is in the menu under your name.',
            'actions' => [
                ['Sign out', 'Signs out one device.'],
                ['Sign out all other devices', 'Keeps only this device signed in. Asks for your password.'],
            ],
            'tips' => [
                'If you don’t recognise a device, sign it out and change your password.',
            ],
        ],

        'screen_kitchen' => [
            'title' => 'Kitchen',
            'summary' => 'The orders to prepare in three columns, New, Cooking and Ready, with each item and its options.',
            'when' => 'On a screen in the kitchen, all through service.',
            'actions' => [
                ['Start', 'Moves the order to Cooking and tells the guest.'],
                ['Ready', 'Moves the order to Ready and tells the window and the guest.'],
                ['Coming up', 'Scheduled orders, with the minutes until they should start.'],
                ['Recently cancelled', 'Orders cancelled a moment ago, so nothing is cooked for nobody.'],
            ],
            'tips' => [
                'Orders change colour as they wait longer than the quoted time.',
            ],
        ],

        'screen_live' => [
            'title' => 'Live orders',
            'summary' => 'Every order at the branch with the guest, car, total, payment and waiting time.',
            'when' => 'For the shift manager to follow the whole branch.',
            'actions' => [
                ['Show', 'Filters to New, Cooking, Ready, Late, Later & unpaid, Completed or Cancelled.'],
                ['List / Boxes', 'Switches between a table and cards.'],
                ['Guest is here', 'Marks that the guest has arrived.'],
                ['Collect', 'Records a cash or card payment.'],
                ['Cancel order / Refund payment', 'Cancels with a reason and returns the payment.'],
                ['Details', 'Opens the full order.'],
            ],
            'tips' => [
                'Every change appears on every screen and on the guest’s phone at the same moment.',
            ],
        ],

        'screen_pickup' => [
            'title' => 'Pickup window',
            'summary' => 'Hands the right order to the right car: scan or type the guest’s code, check, hand over.',
            'when' => 'At the window, for every handover.',
            'actions' => [
                ['Scan / Find', 'Finds the order from the guest’s QR, their 6-digit pickup code or the order code.'],
                ['Handover checks', 'Paid, verified and ready must all be done before handover.'],
                ['Verify', 'Checks the pickup code the guest tells you.'],
                ['Complete handover', 'Completes the order.'],
                ['Guests at the branch', 'Guests who said they have arrived, with their lane.'],
            ],
            'tips' => [
                'Orders that are ready but whose guest isn’t here yet are listed separately.',
            ],
        ],

        'screen_tv' => [
            'title' => 'Command center',
            'summary' => 'A big-screen view of the branch: in the kitchen, ready, late, guests waiting and today’s sales.',
            'when' => 'On a TV in the back office, so everyone sees how the shift is going.',
            'actions' => [
                ['Right now', 'Orders in the kitchen, ready, late and guests waiting.'],
                ['Sales by hour', 'Today compared with yesterday, hour by hour.'],
                ['Alerts', 'What needs attention, or “All good”.'],
            ],
            'tips' => [
                'Nothing needs touching: it updates by itself.',
            ],
        ],

        'screen_pager' => [
            'title' => 'Pager counter',
            'summary' => 'Order-Ready Call: a guest who orders at the counter scans a ticket, and their phone rings when it’s ready.',
            'when' => 'At the counter, for guests who order in person and wait nearby.',
            'actions' => [
                ['New ticket', 'Optionally note what they ordered and a phone for WhatsApp, then tap Give ticket.'],
                ['Show QR / Print ticket', 'The guest scans it with their phone camera.'],
                ['Ready: call guest', 'Makes the guest’s phone ring. Call again if they don’t come.'],
                ['Collected', 'Closes the ticket.'],
            ],
            'tips' => [
                'The guest needs no app and no sign-in, just their phone camera.',
            ],
        ],

        'screen_ready' => [
            'title' => 'Ready board',
            'summary' => 'A screen for guests with the numbers being prepared and the numbers ready.',
            'when' => 'On a screen guests can see at the counter or in the waiting area.',
            'actions' => [
                ['Preparing / Ready', 'Numbers move across by themselves; the newest ready number stands out.'],
            ],
            'tips' => [
                'It shows numbers only, never names or phone numbers.',
            ],
        ],

        'screen_stamps' => [
            'title' => 'Stamp cards',
            'summary' => 'Stamps at the till: open a guest’s cards by phone number, or approve the stamp they asked for.',
            'when' => 'At the till, for guests who pay in person.',
            'actions' => [
                ['Open their cards', 'Type the guest’s phone to see their cards and free items.'],
                ['Add stamps', 'Adds one or more stamps; a full card puts the reward in their wallet.'],
                ['Approve +1 / Decline', 'Answers a guest who scanned the stamp QR at the till.'],
                ['Give now', 'Hands over a free item and marks it used.'],
            ],
            'tips' => [
                'The daily limit on each card stops stamps being added twice by mistake.',
            ],
        ],

        'guest_ordering' => [
            'title' => 'Ordering',
            'summary' => 'Guests open your link or QR, pick a branch, order, pay and say when they arrive.',
            'when' => 'Try it yourself from your own phone before you launch.',
            'actions' => [
                ['Branch', 'Guests choose the nearest open branch.'],
                ['Menu and checkout', 'They pick items and options, add their car and pay.'],
                ['Arrival', 'They tell your window they are here, so the order comes out on time.'],
            ],
            'tips' => [
                'Guests can add it to their home screen like an app.',
            ],
        ],

        'guest_order' => [
            'title' => 'The order page',
            'summary' => 'After ordering, guests follow every step live, then rate the order or report a problem.',
            'when' => 'Guests reach it from the link in their confirmation message.',
            'actions' => [
                ['Live status', 'Shows the order moving from received to ready.'],
                ['Rate', 'Stars and a few taps on what they liked, within 48 hours.'],
                ['Report a problem', 'Opens a conversation with you, which you answer in Support.'],
            ],
            'tips' => [
                'Your replies from Support appear here and reach the guest’s phone.',
            ],
        ],

        'guest_rewards' => [
            'title' => 'Rewards page',
            'summary' => 'Where guests see their points, tier, stamp cards, badges, invite link and rewards ready to use.',
            'when' => 'Guests open it from the tab bar at the bottom of the menu.',
            'actions' => [
                ['Wallet', 'Rewards they can use on their next order.'],
                ['Achievements', 'Badges and weekly streaks, once you switch badges on.'],
                ['Birthday', 'Guests add their birthday to receive your gift.'],
                ['Invite friends', 'Their personal invite link, once invites are on.'],
            ],
            'tips' => [
                'What appears depends on what you switch on in Loyalty and Referrals.',
            ],
        ],

        'guest_invite' => [
            'title' => 'Inviting a friend',
            'summary' => 'Guests share their own invite link from the Rewards page; the friend sees a welcome reward waiting.',
            'when' => 'Once you switch invites on in Referrals.',
            'actions' => [
                ['Share', 'Sends the link by WhatsApp or any app on the phone.'],
                ['Welcome', 'The friend opens the link and signs in to claim the reward on their first order.'],
            ],
            'tips' => [
                'Each friend can be invited only once, and never by themselves.',
            ],
        ],
    ],
];
