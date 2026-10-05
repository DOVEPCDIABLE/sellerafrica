<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;

$brand = app_branding();
$route = trim((string)($_GET['route'] ?? 'knowledge-base'), '/');
if ($route === 'distributors') $route = 'distribution-partner';
$contact = [
    ['label' => 'WhatsApp', 'value' => '+1 (305) 761-7075', 'url' => 'https://wa.me/13057617075'],
    ['label' => 'Email', 'value' => 'hello@sellerafrica.com', 'url' => 'mailto:hello@sellerafrica.com'],
];
$communityLinks = ['Join Our Community', 'Rewards Program', 'Become a Vendor', 'Become a Distribution Partner', 'Vendor Resources'];

$pages = [
    'join-community' => [
        'title' => 'Join Our Community',
        'eyebrow' => 'Community',
        'subtitle' => 'Connect. Grow. Thrive Together.',
        'image' => app_url('assets/images/community.jpeg'),
        'intro' => [
            'Welcome to the Seller Africa Community, a growing network of entrepreneurs, vendors, buyers, farmers, distributors, and business professionals working together to expand African and Caribbean commerce across North America and beyond.',
            'Whether you are looking to grow your business, discover new opportunities, or connect with like-minded professionals, our community is the place to be.',
        ],
        'primaryButton' => ['label' => 'Join the Seller Africa Community', 'url' => app_url('buyer/register')],
        'sections' => [
            ['type' => 'list', 'title' => 'Why Join Our Community?', 'body' => ['As a member, you will gain access to:'], 'items' => ['Exclusive business updates', 'New product and vendor announcements', 'Export and market-entry opportunities', 'Educational webinars and training sessions', 'Marketplace tips and best practices', 'Wholesale and distribution opportunities', 'Networking with entrepreneurs and industry experts', 'Promotions, giveaways, and community events']],
            ['type' => 'list', 'title' => 'Who Can Join?', 'items' => ['African and Caribbean entrepreneurs', 'Food manufacturers and producers', 'Farmers and cooperatives', 'Vendors and retailers', 'Restaurants and food service businesses', 'Wholesalers and distributors', 'Importers and exporters', 'Consumers who love the African and Caribbean Marketplace']],
            ['type' => 'cards', 'title' => 'Community Benefits', 'items' => [
                ['title' => 'Learn', 'body' => ['Access educational resources, business guides, and industry insights to help grow your business.']],
                ['title' => 'Connect', 'body' => ['Build relationships with buyers, suppliers, distributors, and other members of the Seller Africa ecosystem.']],
                ['title' => 'Grow', 'body' => ['Discover new sales opportunities, partnerships, and international markets.']],
                ['title' => 'Stay Informed', 'body' => ['Receive updates on new platform features, upcoming shipments, vendor opportunities, and special promotions.']],
            ]],
            ['type' => 'list', 'title' => 'Community Guidelines', 'body' => ['To maintain a positive and professional environment, all members are expected to:'], 'items' => ['Treat everyone with courtesy and respect.', 'Communicate professionally.', 'Share accurate and truthful information.', 'Avoid spam, harassment, or abusive language.', 'Respect the privacy of other members.', 'Comply with Seller Africa Terms & Conditions.', 'Seller Africa reserves the right to remove any member whose conduct violates these guidelines or disrupts the community.']],
            ['type' => 'list', 'title' => 'Stay Connected', 'body' => ['Follow Seller Africa on social media for the latest updates, educational content, vendor success stories, and marketplace news. You will also receive announcements about:'], 'items' => ['New vendor opportunities', 'Product launches', 'Trade events', 'Business webinars', 'Export programs', 'Marketplace promotions']],
            ['type' => 'contact', 'title' => 'Need assistance?', 'body' => ['Our support team is always ready to help.'], 'items' => $contact],
        ],
        'cta' => ['title' => 'Become Part of the Movement', 'body' => ['Join thousands of entrepreneurs, businesses, and customers who are helping build a stronger future for African and Caribbean trade.', 'Together, we are connecting Africa to global markets.'], 'button' => ['label' => 'Join the Seller Africa Community', 'url' => app_url('buyer/register')]],
    ],
    'rewards-program' => [
        'title' => 'Seller Africa Rewards Program',
        'eyebrow' => 'Rewards Program',
        'subtitle' => 'Earn Rewards by Growing the Seller Africa Community',
        'intro' => ['The Seller Africa Rewards Program allows you to earn commissions by referring vendors, customers, and business partners to the Seller Africa marketplace.', 'Whether you are a content creator, entrepreneur, student, influencer, or community leader, you can turn your network into a source of income.'],
        'primaryButton' => ['label' => 'Join the Rewards Program', 'url' => app_url('affiliate/join')],
        'sections' => [
            ['type' => 'steps', 'title' => 'How It Works', 'items' => [
                ['title' => 'Join the Program', 'body' => ['Sign up for free and become a Seller Africa Rewards Partner.'], 'button' => ['label' => 'Join the Rewards Program', 'url' => app_url('affiliate/join')]],
                ['title' => 'Share Your Referral Link', 'body' => ['Receive your unique referral link to share through social media, WhatsApp, email, blogs, YouTube, TikTok, LinkedIn, and community groups.']],
                ['title' => 'Earn Commissions', 'body' => ['Receive rewards when someone signs up or completes eligible actions through your referral link. Eligible commissions may come from vendor subscriptions, qualified business referrals, approved partnership referrals, and special campaigns.']],
            ]],
            ['type' => 'list', 'title' => 'Who Can Join?', 'items' => ['Content creators', 'Influencers', 'Entrepreneurs', 'Students', 'Community leaders', 'Sales professionals', 'Marketing agencies', 'Existing Seller Africa vendors', 'Anyone passionate about supporting African businesses']],
            ['type' => 'list', 'title' => 'Why Join?', 'items' => ['Competitive commission opportunities', 'Free registration', 'Personal referral dashboard', 'Marketing materials and promotional assets', 'Performance bonuses during special campaigns', 'Early access to new Seller Africa initiatives', 'Dedicated affiliate support']],
            ['type' => 'list', 'title' => 'Rewards Dashboard', 'body' => ['Track your progress in real time:'], 'items' => ['Total referrals', 'Qualified referrals', 'Commission earned', 'Pending payouts', 'Payment history', 'Referral performance']],
            ['type' => 'text', 'title' => 'Payouts', 'body' => ['Approved commissions are paid according to the Seller Africa payout schedule after referred transactions meet eligibility requirements and any applicable verification or return periods have passed.']],
            ['type' => 'list', 'title' => 'Program Guidelines', 'items' => ['Promote Seller Africa honestly and ethically.', 'Avoid misleading advertising or false claims.', 'Refrain from spam or unsolicited marketing.', 'Comply with all applicable laws and platform policies.', 'Follow the Seller Africa Affiliate Program Terms.', 'Seller Africa reserves the right to suspend or terminate accounts that violate these guidelines.']],
            ['type' => 'faq', 'title' => 'Frequently Asked Questions', 'items' => [
                ['q' => 'Is it free to join?', 'a' => 'Yes. Joining the Seller Africa Rewards Program is completely free.'],
                ['q' => 'Is there a limit to how much I can earn?', 'a' => 'No. Your earning potential depends on the number and quality of eligible referrals you generate.'],
                ['q' => 'How do I know if someone used my referral link?', 'a' => 'All qualifying referrals are tracked through your affiliate dashboard.'],
                ['q' => 'When do I get paid?', 'a' => 'Eligible commissions are paid according to our payout schedule after verification.'],
            ]],
            ['type' => 'contact', 'title' => 'Need Help?', 'body' => ['For questions about the Rewards Program:'], 'items' => $contact],
        ],
        'cta' => ['title' => 'Start Earning Today', 'body' => ['Help African and Caribbean businesses reach global markets while earning rewards for every successful referral.'], 'button' => ['label' => 'Become a Rewards Partner', 'url' => app_url('affiliate/join')]],
    ],
    'become-a-vendor' => [
        'title' => 'Become a Vendor',
        'eyebrow' => 'Vendor Program',
        'subtitle' => 'Grow Your Business Beyond Borders',
        'image' => app_url('assets/images/vendor_reg.jpeg'),
        'intro' => ['Join Seller Africa and connect your business with customers across the United States, Canada, and other international markets. Whether you are a food producer, manufacturer, wholesaler, artisan, or retailer, our platform helps you expand your reach and grow your sales.'],
        'primaryButton' => ['label' => 'Apply as Vendor', 'url' => app_url('vendor/register')],
        'sections' => [
            ['type' => 'cards', 'title' => 'Why Sell on Seller Africa?', 'body' => ['We provide the tools and support you need to take your business global.'], 'items' => [
                ['title' => 'Reach More Customers', 'body' => ['Showcase your products to a growing community of African, Caribbean, and international buyers.']],
                ['title' => 'Expand into New Markets', 'body' => ['Access customers across North America without building your own international sales infrastructure.']],
                ['title' => 'Secure Payments', 'body' => ['Receive payments through secure and trusted payment systems.']],
                ['title' => 'Marketing & Visibility', 'body' => ['Increase your brand exposure through featured listings, promotional campaigns, and marketplace advertising.']],
                ['title' => 'Export & Logistics Support', 'body' => ['Access guidance on shipping, documentation, and international fulfillment.']],
                ['title' => 'Business Growth Resources', 'body' => ['Learn from webinars, training sessions, and expert resources designed to help your business succeed.']],
            ]],
            ['type' => 'list', 'title' => 'Who Can Become a Vendor?', 'items' => ['Food manufacturers', 'Farmers and agricultural cooperatives', 'Beverage producers', 'Spice and seasoning brands', 'Beauty and personal care brands', 'Fashion and apparel businesses', 'Home and lifestyle brands', 'Health and wellness businesses', 'Wholesalers and distributors']],
            ['type' => 'steps', 'title' => 'How to Get Started', 'items' => [
                ['title' => 'Create Your Account', 'body' => ['Register as a vendor and complete your business profile.']],
                ['title' => 'Verify Your Business', 'body' => ['Provide the required business information and supporting documents for verification.']],
                ['title' => 'Set Up Your Store', 'body' => ['Upload your products, pricing, inventory, and product images.']],
                ['title' => 'Start Selling', 'body' => ['Once approved, your products will be published on the Seller Africa marketplace and made available to customers.']],
            ]],
            ['type' => 'cards', 'title' => 'Vendor Subscription Plans', 'body' => ['Choose a plan that matches your business goals and enjoy benefits such as:'], 'items' => [
                ['title' => 'Plan Benefits', 'items' => ['Homepage product placement', 'Increased product visibility', 'Marketing support', 'Store optimization recommendations', 'Priority customer support', 'Exclusive promotional opportunities'], 'button' => ['label' => 'View Subscription Plans', 'url' => app_url('packages')]],
            ]],
            ['type' => 'list', 'title' => 'Vendor Requirements', 'items' => ['Provide accurate product information.', 'Upload high-quality product images.', 'Maintain sufficient inventory.', 'Fulfill orders promptly.', 'Comply with applicable food safety, labeling, and import/export regulations.', 'Deliver excellent customer service.']],
            ['type' => 'list', 'title' => 'Why Vendors Choose Seller Africa', 'items' => ['Access to international buyers', 'Marketplace built for African and Caribbean businesses', 'Export and logistics support', 'Marketing and promotional opportunities', 'Dedicated vendor support', 'Scalable business growth solutions']],
            ['type' => 'faq', 'title' => 'Frequently Asked Questions', 'items' => [
                ['q' => 'Is there a fee to become a vendor?', 'a' => 'Creating a vendor account is free. Optional subscription plans are available for vendors who want additional visibility, promotional opportunities, and business support.'],
                ['q' => 'Can I sell from outside Africa?', 'a' => 'Yes. We welcome businesses from Africa, the Caribbean, and other regions that fit the African and Caribbean Marketplace.'],
                ['q' => 'Do I need a registered business?', 'a' => 'While registration requirements may vary by product category and location, business verification may be required before selling on the platform.'],
                ['q' => 'Can Seller Africa help me export my products?', 'a' => 'Yes. We provide export guidance, logistics support, and market access solutions to help qualified vendors expand internationally.'],
            ]],
            ['type' => 'contact', 'title' => 'Need Assistance?', 'body' => ['Our Vendor Success Team is ready to help you get started.'], 'items' => $contact],
        ],
        'cta' => ['title' => 'Start Selling Globally', 'body' => ['Join thousands of businesses using Seller Africa to reach new customers, expand into international markets, and grow with confidence.'], 'button' => ['label' => 'Apply as Vendor', 'url' => app_url('vendor/register')]],
    ],
    'distribution-partner' => [
        'title' => 'Become a Distribution Partner',
        'eyebrow' => 'Distribution Partner',
        'subtitle' => 'Help Bring African Brands to More Communities',
        'intro' => ['Join the Seller Africa Distribution Partner Network and help expand the African and Caribbean Marketplace across North America. Whether you are a wholesaler, retailer, logistics provider, or regional distributor, we are looking for trusted partners to expand market access for quality brands.'],
        'primaryButton' => ['label' => 'Apply to Become a Distribution Partner', 'url' => app_url('distributor/register')],
        'sections' => [
            ['type' => 'cards', 'title' => 'Why Partner with Seller Africa?', 'body' => ['As a Distribution Partner, you will play a key role in connecting African products with retailers, restaurants, and consumers.'], 'items' => [
                ['title' => 'Access High-Demand Products', 'body' => ['Distribute a growing portfolio from African and Caribbean food brands and specialty producers.']],
                ['title' => 'Exclusive Business Opportunities', 'body' => ['Receive early access to new product launches and regional distribution opportunities.']],
                ['title' => 'Expand Your Product Portfolio', 'body' => ['Add unique, in-demand products that serve the growing African and Caribbean diaspora market.']],
                ['title' => 'Reliable Supply Chain', 'body' => ['Benefit from our sourcing network, logistics support, and fulfillment capabilities.']],
                ['title' => 'Dedicated Business Support', 'body' => ['Our team works with you to ensure a smooth onboarding process and continued business growth.']],
            ]],
            ['type' => 'list', 'title' => 'Who Can Apply?', 'items' => ['Food distributors', 'Wholesale businesses', 'Grocery store chains', 'Independent supermarkets', 'African and Caribbean grocery stores', 'Restaurant suppliers', 'Hospitality and food service distributors', 'Importers and exporters', 'E-commerce fulfillment companies', 'Regional sales representatives']],
            ['type' => 'list', 'title' => 'Partnership Opportunities', 'items' => ['Regional distribution rights', 'Wholesale purchasing', 'Retail supply partnerships', 'Restaurant and food service supply', 'Private label sourcing', 'Warehouse and fulfillment collaboration', 'Market expansion initiatives']],
            ['type' => 'list', 'title' => 'What You Will Receive', 'items' => ['Wholesale pricing', 'Marketing and promotional support', 'Product catalogs and sales materials', 'Dedicated account management', 'Product launch notifications', 'Business development opportunities', 'Training and onboarding support']],
            ['type' => 'list', 'title' => 'Partner Requirements', 'items' => ['Operate a legally registered business.', 'Have experience in wholesale, retail, or product distribution.', 'Demonstrate the ability to manage inventory and customer relationships.', 'Comply with all applicable food safety, import, and distribution regulations.', 'Maintain professional business practices and ethical standards.']],
            ['type' => 'steps', 'title' => 'How to Apply', 'items' => [
                ['title' => 'Submit Your Application', 'body' => ['Provide your business details, areas of operation, and distribution experience.']],
                ['title' => 'Business Review', 'body' => ['Our team will review your application to determine alignment with our distribution network.']],
                ['title' => 'Partnership Discussion', 'body' => ['Qualified applicants will be contacted to discuss available products, territories, and partnership opportunities.']],
                ['title' => 'Onboarding', 'body' => ['Once approved, you will receive onboarding, product information, and access to begin distributing Seller Africa products.']],
            ]],
            ['type' => 'faq', 'title' => 'Frequently Asked Questions', 'items' => [
                ['q' => 'Do I need an existing warehouse?', 'a' => 'Not necessarily. Warehouse capacity is beneficial but not required for every partnership. We assess each application based on the proposed distribution model.'],
                ['q' => 'Is there an application fee?', 'a' => 'No. There is no fee to apply. Partnership opportunities are subject to review and approval.'],
                ['q' => 'Can I distribute in more than one region?', 'a' => 'Yes. Multi-region opportunities may be available based on your operational capacity and market coverage.'],
                ['q' => 'What products will I distribute?', 'a' => 'Product availability depends on market demand and supplier availability. We offer a range of African and Caribbean marketplace goods and related consumer products.'],
            ]],
            ['type' => 'contact', 'title' => 'Need More Information?', 'body' => ['Our Business Development Team is available to answer your questions.'], 'items' => $contact],
        ],
        'cta' => ['title' => 'Let Us Grow Together', 'body' => ['At Seller Africa, we are building a trusted distribution network that connects African producers with customers across North America. If you are ready to expand your business while bringing authentic products to more communities, we would love to partner with you.'], 'button' => ['label' => 'Become a Distribution Partner', 'url' => app_url('distributor/register')]],
    ],
    'vendor-resources' => [
        'title' => 'Vendor Resources',
        'eyebrow' => 'Resources',
        'subtitle' => 'Tools, Guides & Resources to Help You Grow',
        'intro' => ['The Seller Africa Vendor Resources Center provides practical tools, educational materials, and business support to help vendors build successful stores, increase sales, and expand into international markets.', 'Whether you are just getting started or looking to scale your business, you will find everything you need in one place.'],
        'primaryButton' => ['label' => 'Start Selling', 'url' => app_url('vendor/register')],
        'sections' => [
            ['type' => 'cards', 'title' => 'Resource Center', 'items' => [
                ['title' => 'Getting Started', 'body' => ['New to Seller Africa? Begin here.'], 'items' => ['Vendor Registration Guide', 'Store Setup Guide', 'Product Listing Guide', 'Account Verification Process', 'Vendor Subscription Plans'], 'button' => ['label' => 'Start Selling', 'url' => app_url('vendor/register')]],
                ['title' => 'Selling Guides', 'body' => ['Learn how to create a successful online store.'], 'items' => ['How to Create High-Converting Product Listings', 'Writing Effective Product Descriptions', 'Product Photography Tips', 'Pricing Your Products', 'Inventory Management Best Practices']],
                ['title' => 'Export Resources', 'body' => ['Prepare your products for international markets.'], 'items' => ['Export Readiness Checklist', 'Product Labeling Requirements', 'Packaging Best Practices', 'Customs Documentation', 'Food Safety & Compliance', 'UPC Barcode Guidance', 'Traceability & Batch Management']],
                ['title' => 'Shipping & Logistics', 'body' => ['Everything you need to know about shipping with Seller Africa.'], 'items' => ['Shipping Process', 'Delivery Timelines', 'Packaging Standards', 'Warehouse Services', 'Shipment Preparation Guide', 'International Shipping Tips']],
                ['title' => 'Marketing Resources', 'body' => ['Grow your brand and increase sales.'], 'items' => ['Social Media Marketing', 'Product Promotions', 'Homepage Featured Listings', 'Running discounts & campaigns', 'Seasonal Sales Planning', 'Building Customer Trust']],
                ['title' => 'Business Growth', 'body' => ['Take your business to the next level.'], 'items' => ['Selling to the African Diaspora', 'Entering the U.S. Market', 'Wholesale & Distribution Opportunities', 'Retail Readiness', 'Business Scaling Strategies', 'Brand Development']],
                ['title' => 'Seller Africa Academy Coming Soon', 'body' => ['Access free educational content designed to help vendors succeed.'], 'items' => ['Video Tutorials', 'Live Webinars', 'Trade Thursday Sessions', 'Business Templates', 'Downloadable Guides', 'Expert Interviews']],
                ['title' => 'Downloads', 'body' => ['Helpful documents and templates.'], 'items' => ['Product Listing Template', 'Pricing Calculator', 'Export Readiness Checklist', 'Packaging Checklist', 'Shipping Label Template', 'Inventory Tracker', 'Vendor Handbook']],
                ['title' => 'Frequently Asked Questions', 'body' => ['Find answers to common questions about vendor accounts, shipping, payments, product listings, marketplace policies, and subscription plans.'], 'button' => ['label' => 'Visit FAQs', 'url' => app_url('faqs')]],
            ]],
            ['type' => 'contact', 'title' => 'Need Help?', 'body' => ['Our Vendor Success Team is here to support you.'], 'items' => $contact],
        ],
        'cta' => ['title' => 'Helping African Businesses Grow Globally', 'body' => ['At Seller Africa, we do not just provide a marketplace. We equip vendors with the knowledge, tools, and support they need to succeed in international markets.'], 'button' => ['label' => 'Explore Vendor Resources', 'url' => app_url('vendor-resources')]],
    ],
    'knowledge-base' => [
        'title' => 'Help Center',
        'eyebrow' => 'Help Center',
        'subtitle' => 'How Can We Help?',
        'intro' => ['Welcome to the Seller Africa Help Center. Browse our knowledge base to find answers, learn how to use the marketplace, and get the support you need.'],
        'primaryButton' => ['label' => 'Contact Customer Support', 'url' => app_url('customer-support')],
        'secondaryButton' => ['label' => 'Submit a Support Request', 'url' => app_url('contact')],
        'sections' => [
            ['type' => 'cards', 'title' => 'Browse Help Topics', 'items' => [
                ['title' => 'Getting Started', 'body' => ['New to Seller Africa? Start here.'], 'items' => ['Vendor Registration Guide', 'Store Setup Guide', 'Product Listing Guide', 'Account Verification', 'Vendor Subscription Plans'], 'button' => ['label' => 'Get Started', 'url' => app_url('vendor/register')]],
                ['title' => 'Selling Guides', 'body' => ['Learn how to build and grow a successful store.'], 'items' => ['How to Create High-Converting Product Listings', 'Writing Effective Product Descriptions', 'Product Photography Tips', 'Pricing Your Products', 'Inventory Management Best Practices'], 'button' => ['label' => 'View Selling Guides', 'url' => app_url('vendor-resources')]],
                ['title' => 'Shipping & Logistics', 'body' => ['Everything you need to know about shipping and fulfillment.'], 'items' => ['Shipping & Delivery', 'Order Tracking', 'Returns & Refunds'], 'button' => ['label' => 'Shipping Support', 'url' => app_url('shipping-delivery')]],
                ['title' => 'Marketplace Policies', 'body' => ['Understand your rights and responsibilities.'], 'items' => ['Terms & Conditions', 'Privacy Policy', 'Vendor Agreement', 'Partner Agreement', 'Cookie Policy'], 'button' => ['label' => 'View Policies', 'url' => app_url('terms')]],
                ['title' => 'Frequently Asked Questions', 'body' => ['Find quick answers about shopping, selling, payments, shipping, account verification, subscriptions, and marketplace policies.'], 'items' => ['Shopping', 'Selling', 'Payments', 'Shipping', 'Account Verification', 'Vendor Subscriptions', 'Marketplace Policies'], 'button' => ['label' => 'Browse FAQs', 'url' => app_url('faqs')]],
                ['title' => 'Community & Business Growth', 'body' => ['Discover programs designed to help your business grow.'], 'items' => $communityLinks, 'button' => ['label' => 'Explore Community', 'url' => app_url('join-community')]],
                ['title' => 'Company Information', 'body' => ['Learn more about Seller Africa.'], 'items' => ['About Seller Africa', 'Our Vendors', 'Careers', 'Blog & News', 'Contact Us'], 'button' => ['label' => 'Learn More', 'url' => app_url('about')]],
            ]],
            ['type' => 'contact', 'title' => 'Contact Support', 'body' => ['Cannot find what you are looking for? Our support team is here to help.'], 'items' => $contact],
        ],
        'cta' => ['title' => 'Still Need Help?', 'body' => ['If you could not find the answer you are looking for, contact our support team and we will be happy to assist you.'], 'button' => ['label' => 'Contact Customer Support', 'url' => app_url('customer-support')]],
    ],
];

$simplePages = [
    'faqs' => ['FAQs', 'Frequently asked questions about shopping, selling, payments, shipping, account verification, vendor subscriptions, and marketplace policies.'],
    'shipping-delivery' => ['Shipping & Delivery', 'Find information about delivery timelines, shipment preparation, warehouse services, and international shipping support.'],
    'returns-refunds' => ['Returns & Refunds', 'Learn how Seller Africa handles returns, refunds, product issues, and order support requests.'],
    'customer-support' => ['Customer Support', 'Contact our support team for help with orders, vendor accounts, payments, shipping, and marketplace questions.'],
    'careers' => ['Careers', 'Join Seller Africa as we build commerce, logistics, and distribution infrastructure for the African and Caribbean Marketplace.'],
    'blog-news' => ['Blog & News', 'Read Seller Africa updates, vendor stories, market insights, trade news, and community announcements.'],
    'vendor-agreement' => ['Vendor Agreement', 'Review the core expectations for vendors selling through Seller Africa, including product accuracy, fulfillment, compliance, and customer service.'],
    'partner-agreement' => ['Partner Agreement', 'Review expectations for business and distribution partners working with Seller Africa.'],
    'cookie-policy' => ['Cookie Policy', 'Learn how Seller Africa may use cookies and similar technologies to support site performance, account sessions, analytics, and marketplace improvements.'],
];

foreach ($simplePages as $simpleRoute => [$simpleTitle, $simpleDescription]) {
    $pages[$simpleRoute] = [
        'title' => $simpleTitle,
        'eyebrow' => 'Seller Africa',
        'intro' => [$simpleDescription],
        'primaryButton' => ['label' => 'Contact Us', 'url' => app_url('contact')],
        'sections' => [
            ['type' => 'contact', 'title' => 'Need assistance?', 'body' => ['Our team is ready to help.'], 'items' => $contact],
        ],
    ];
}

$thirdPartyProviderTerms = [
    'type' => 'legal',
    'title' => 'Third-Party Service Providers & Limitation of Liability',
    'body' => ['Seller Africa Vendor Terms & Conditions'],
    'items' => [
        [
            'title' => 'Independent Service Providers',
            'body' => [
                'Seller Africa may, from time to time, introduce vendors to independent third-party service providers offering services including, but not limited to, branding, packaging, labeling, sourcing, export documentation, freight forwarding, customs clearance, logistics, warehousing, photography, and other related business services.',
                'These service providers operate as independent businesses and are not employees, agents, partners, subsidiaries, affiliates, or representatives of Seller Africa. Any agreement entered into between a vendor and a third-party service provider is a separate contractual relationship between those parties.',
            ],
        ],
        [
            'title' => 'No Partnership or Agency',
            'body' => [
                'The introduction of any third-party service provider by Seller Africa shall not be construed as an endorsement, partnership, joint venture, agency, or guarantee of the provider’s services, performance, conduct, pricing, financial stability, or business practices.',
            ],
        ],
        [
            'title' => 'Vendor Due Diligence',
            'body' => [
                'Vendors are solely responsible for conducting their own due diligence before engaging any third-party service provider. Seller Africa strongly recommends that vendors independently verify the provider’s identity, qualifications, business registration, pricing, insurance (where applicable), and suitability for the required services before entering into any transaction.',
            ],
        ],
        [
            'title' => 'Limitation of Liability',
            'body' => [
                'Seller Africa shall not be liable for any loss, theft, damage, delay, negligence, breach of contract, fraud, misrepresentation, service failure, or any other dispute arising from or relating to a vendor’s engagement with an independent third-party service provider.',
                'Any dispute concerning services provided by a third-party provider shall be resolved directly between the vendor and the service provider. Seller Africa shall have no obligation to compensate, reimburse, or indemnify either party for losses arising from such transactions.',
            ],
        ],
        [
            'title' => 'Recovery and Legal Cooperation',
            'body' => [
                'Where Seller Africa becomes aware of suspected theft, fraud, unauthorized possession of goods, or any criminal activity involving a third-party service provider or any current or former employee, Seller Africa may report the matter to the appropriate law enforcement authorities and cooperate fully with any investigation.',
                'Affected vendors acknowledge that they remain the legal owners of their goods and may pursue independent legal remedies, including filing police reports or civil claims, against the responsible parties. Seller Africa’s cooperation with any investigation does not constitute an admission of liability.',
            ],
        ],
        [
            'title' => 'No Guarantee of Third-Party Performance',
            'body' => [
                'Seller Africa does not warrant or guarantee the quality, timeliness, safety, security, availability, pricing, or outcome of services provided by any third-party provider. Vendors engage such providers entirely at their own discretion and risk.',
            ],
        ],
        [
            'title' => 'Changes to Service Providers',
            'body' => [
                'Seller Africa reserves the right to add, remove, suspend, or discontinue recommending any third-party service provider at any time without prior notice and without liability to vendors.',
            ],
        ],
        [
            'title' => 'Indemnification',
            'body' => [
                'Vendors agree to indemnify and hold Seller Africa, its directors, officers, employees, and affiliates harmless from any claims, liabilities, damages, losses, legal costs, or expenses arising out of or relating to their dealings with independent third-party service providers, except to the extent such losses are directly caused by Seller Africa’s proven gross negligence or willful misconduct.',
            ],
        ],
    ],
];

$pages['vendor-agreement']['title'] = 'Seller Africa Vendor Terms & Conditions';
$pages['vendor-agreement']['intro'][] = 'These vendor terms include responsibilities for engagements with independent third-party service providers introduced through or around the Seller Africa marketplace.';
array_unshift($pages['vendor-agreement']['sections'], $thirdPartyProviderTerms);

$latestBlogPosts = table_exists('content_posts') ? db()->fetchAll(
    "SELECT title, excerpt, category, published_at
     FROM content_posts
     WHERE status = 'published'
     ORDER BY COALESCE(published_at, created_at) DESC
     LIMIT 6"
) : [];
$latestBlogItems = [];
foreach ($latestBlogPosts as $post) {
    $latestBlogItems[] = [
        'title' => (string)$post['title'],
        'body' => [(string)($post['excerpt'] ?: 'Read the latest Seller Africa marketplace update, trade insight, or community story.')],
        'items' => array_filter([(string)($post['category'] ?: 'Blog & News'), !empty($post['published_at']) ? date('M j, Y', strtotime((string)$post['published_at'])) : '']),
    ];
}
if ($latestBlogItems === []) {
    $latestBlogItems = [
        ['title' => 'Marketplace Announcements', 'body' => ['Updates on platform features, vendor programs, partnerships, and community news.']],
        ['title' => 'Export Opportunities', 'body' => ['Resources on international shipping, customs, packaging standards, and market access.']],
        ['title' => 'Vendor Success Tips', 'body' => ['Practical strategies for store optimization, product marketing, and customer service.']],
    ];
}

$pages['careers'] = [
    'title' => 'Careers at Seller Africa',
    'eyebrow' => 'Careers',
    'subtitle' => 'Build the Future of African Commerce',
    'intro' => [
        'At Seller Africa, we are connecting African businesses with customers across North America through technology, logistics, and global trade.',
        'We are building more than an e-commerce marketplace. We are creating opportunities for entrepreneurs, farmers, manufacturers, and businesses to reach international markets.',
        'If you are passionate about innovation, customer success, and making a global impact, we would love to hear from you.',
    ],
    'primaryButton' => ['label' => 'View Open Positions', 'url' => 'mailto:careers@sellerafrica.com?subject=Open%20Positions'],
    'secondaryButton' => ['label' => 'Submit Your Resume', 'url' => 'mailto:careers@sellerafrica.com?subject=Resume%20Submission'],
    'sections' => [
        ['type' => 'list', 'title' => 'Why Work With Us?', 'body' => ['Join a fast-growing company that is transforming cross-border commerce. As part of the Seller Africa team, you will have the opportunity to:'], 'items' => ['Help African businesses reach global markets', 'Work with a diverse and international team', 'Contribute to innovative technology and logistics solutions', 'Grow your skills in e-commerce, export, and international trade', 'Make a meaningful impact on entrepreneurs and communities']],
        ['type' => 'cards', 'title' => 'Our Values', 'items' => [
            ['title' => 'Customer First', 'body' => ['We are committed to delivering exceptional service and creating value for our customers and vendors.']],
            ['title' => 'Innovation', 'body' => ['We embrace new ideas and continuously improve the way we work.']],
            ['title' => 'Integrity', 'body' => ['We act with honesty, accountability, and professionalism in everything we do.']],
            ['title' => 'Collaboration', 'body' => ['We believe great results come from teamwork, mutual respect, and shared success.']],
            ['title' => 'Excellence', 'body' => ['We strive for quality, efficiency, and continuous improvement.']],
        ]],
        ['type' => 'cards', 'title' => 'Current Opportunities', 'body' => ['We regularly recruit for roles such as:'], 'items' => [
            ['title' => 'Customer Support', 'body' => ['Assist customers and vendors by providing timely, professional support.']],
            ['title' => 'Vendor Success', 'body' => ['Help businesses onboard, grow, and succeed on the Seller Africa marketplace.']],
            ['title' => 'Sales & Business Development', 'body' => ['Build partnerships and expand our marketplace across new regions.']],
            ['title' => 'Marketing & Content', 'body' => ['Create engaging campaigns that promote African businesses and products.']],
            ['title' => 'Operations & Logistics', 'body' => ['Support shipping, fulfillment, and supply chain operations.']],
            ['title' => 'Technology', 'body' => ['Develop and maintain the digital tools that power our marketplace.']],
        ]],
        ['type' => 'list', 'title' => 'Who We Are Looking For', 'items' => ['Customer-focused', 'Passionate about entrepreneurship and global trade', 'Strong communicators', 'Team players', 'Problem solvers', 'Adaptable and eager to learn', 'Committed to delivering excellent results']],
        ['type' => 'list', 'title' => 'Internship Opportunities', 'body' => ['Our internship program helps students and early-career professionals develop practical skills in a fast-paced business environment.'], 'items' => ['Customer Support', 'Marketing', 'Sales', 'Business Development', 'Operations', 'Administration', 'Technology']],
        ['type' => 'text', 'title' => 'Remote Opportunities', 'body' => ['Many of our roles support remote and hybrid work arrangements, allowing talented professionals from different regions to collaborate with our global team. Available work arrangements may vary depending on the position.']],
        ['type' => 'steps', 'title' => 'Recruitment Process', 'items' => [
            ['title' => 'Submit your application.', 'body' => ['Send your resume or apply for a listed opportunity.']],
            ['title' => 'Application review.', 'body' => ['Our team reviews your experience and fit for available roles.']],
            ['title' => 'Interview and assessment.', 'body' => ['Selected candidates may complete interviews, skills assessments, and reference checks.']],
            ['title' => 'Offer and onboarding.', 'body' => ['Successful candidates receive an offer and onboarding guidance.']],
        ]],
        ['type' => 'text', 'title' => 'Equal Opportunity', 'body' => ['Seller Africa is committed to providing equal employment opportunities. We value diversity and welcome qualified applicants from all backgrounds. Employment decisions are based on qualifications, skills, experience, and business needs.']],
        ['type' => 'contact', 'title' => 'Join Our Talent Community', 'body' => ['Submit your resume to join our talent pool, and we will notify you when future opportunities match your skills and experience.'], 'items' => [['label' => 'Email', 'value' => 'careers@sellerafrica.com', 'url' => 'mailto:careers@sellerafrica.com']]],
    ],
    'cta' => ['title' => 'Shape the Future of African Trade', 'body' => ['Whether you are helping vendors succeed, supporting customers, building technology, or expanding our global marketplace, you will play an important role in connecting African businesses with opportunities around the world.'], 'button' => ['label' => 'Apply Now', 'url' => 'mailto:careers@sellerafrica.com?subject=Career%20Application']],
];

$pages['blog-news'] = [
    'title' => 'Blog & News',
    'eyebrow' => 'Insights',
    'subtitle' => 'Insights, Updates, and Stories from Seller Africa',
    'intro' => [
        'Stay informed with the latest marketplace updates, business insights, export resources, and success stories from the Seller Africa community.',
        'Whether you are a vendor, buyer, distributor, or entrepreneur, our Blog & News keeps you connected to opportunities shaping African trade and commerce.',
    ],
    'primaryButton' => ['label' => 'Read Latest Articles', 'url' => '#latest-articles'],
    'secondaryButton' => ['label' => 'Subscribe for Updates', 'url' => 'mailto:hello@sellerafrica.com?subject=Blog%20Updates'],
    'sections' => [
        ['type' => 'cards', 'title' => 'Featured Categories', 'items' => [
            ['title' => 'Marketplace News', 'body' => ['Stay up to date with the latest Seller Africa announcements.'], 'items' => ['New platform features', 'Marketplace updates', 'Vendor programs', 'Community announcements', 'Partnership news']],
            ['title' => 'Export & International Trade', 'body' => ['Learn how to grow your business beyond borders.'], 'items' => ['Export readiness', 'International shipping', 'Customs and documentation', 'Packaging standards', 'Global market opportunities']],
            ['title' => 'Vendor Success', 'body' => ['Discover strategies to grow your online business.'], 'items' => ['Selling tips', 'Product marketing', 'Customer service', 'Store optimization', 'Business growth strategies']],
            ['title' => 'Food & Agriculture', 'body' => ['Explore trends shaping Africa’s food industry.'], 'items' => ['Organic farming', 'Fresh produce exports', 'Food safety', 'Supply chain innovations', 'African food market insights']],
            ['title' => 'Business & Entrepreneurship', 'body' => ['Practical advice for building a successful business.'], 'items' => ['Business planning', 'Branding', 'Marketing', 'Digital commerce', 'Leadership', 'Funding opportunities']],
            ['title' => 'Customer Stories', 'body' => ['Hear from vendors, buyers, and partners who are growing with Seller Africa.'], 'items' => ['Vendor success stories', 'Customer experiences', 'Business milestones', 'Marketplace achievements']],
        ]],
        ['type' => 'cards', 'title' => 'Latest Articles', 'body' => ['Our latest posts cover marketplace announcements, export opportunities, e-commerce best practices, African food trends, distribution and logistics, vendor success, and business insights.'], 'items' => $latestBlogItems],
        ['type' => 'list', 'title' => 'Stay Connected', 'body' => ['Subscribe to receive updates from Seller Africa.'], 'items' => ['Marketplace news', 'Vendor tips', 'Export resources', 'Industry insights', 'New program announcements', 'Special events and webinars']],
        ['type' => 'list', 'title' => 'Contribute to Our Blog', 'body' => ['We welcome guest articles from vendors, exporters, farmers, food producers, industry experts, and business leaders.'], 'items' => ['Vendor stories', 'Business insights', 'Educational articles', 'Export lessons', 'Community milestones']],
        ['type' => 'text', 'title' => 'Follow Our Journey', 'body' => ['Connect with Seller Africa on social media to stay informed about new opportunities, marketplace updates, and inspiring stories from our growing community.']],
    ],
    'cta' => ['title' => 'Learn. Grow. Expand.', 'body' => ['Our Blog & News is more than a collection of articles. It is a resource hub designed to help businesses grow, discover new opportunities, and succeed in international markets.'], 'button' => ['label' => 'Explore the Blog', 'url' => app_url('blog-news')]],
];

$pageData = $pages[$route] ?? $pages['knowledge-base'];
$cart = new CartService();

render_layout('storefront-home', 'storefront/pages/info-page.php', [
    'page' => $route,
    'title' => (string)$pageData['title'] . ' | ' . $brand['name'],
    'metaDescription' => (string)($pageData['subtitle'] ?? ($pageData['intro'][0] ?? 'Seller Africa marketplace resources.')),
    'canonical' => app_url($route),
    'pageData' => $pageData,
    'cartCount' => $cart->count(),
]);
