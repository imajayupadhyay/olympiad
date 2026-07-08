<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSection extends Model
{
    protected $fillable = [
        'key',
        'title',
        'is_enabled',
        'sort_order',
        'content',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
        'content' => 'array',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function ensureDefaults(): void
    {
        foreach (self::defaults() as $index => $section) {
            static::firstOrCreate(
                ['key' => $section['key']],
                [
                    'title' => $section['title'],
                    'is_enabled' => true,
                    'sort_order' => ($index + 1) * 10,
                    'content' => $section['content'],
                ],
            );
        }
    }

    public static function publicPayload(): array
    {
        self::ensureDefaults();

        return static::query()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (self $section) => [
                $section->key => [
                    'is_enabled' => $section->is_enabled,
                    ...$section->content,
                ],
            ])
            ->all();
    }

    public static function defaultFor(string $key): ?array
    {
        return collect(self::defaults())->firstWhere('key', $key);
    }

    public static function defaults(): array
    {
        return [
            [
                'key' => 'seo',
                'title' => 'SEO & Page Meta',
                'content' => [
                    'page_title' => 'Online Olympiad Exams for Class 1-12',
                    'meta_description' => "National Olympiad Hunt is India's online olympiad platform for Class 1-12. Register free, take timed olympiad exams across subjects, climb national ranks and win medals & certificates.",
                ],
            ],
            [
                'key' => 'marquee',
                'title' => 'Top Announcement Bar',
                'content' => [
                    'items' => [
                        ['icon' => 'Trophy', 'text' => 'National Maths Olympiad 2026 -', 'hl' => 'Registrations Open'],
                        ['icon' => 'Students', 'text' => 'Over 1,20,000+ students competing nationwide', 'hl' => ''],
                        ['icon' => 'Medal', 'text' => 'Win scholarships up to', 'hl' => 'Rs 1,00,000'],
                        ['icon' => 'Calendar', 'text' => 'Early-bird closes', 'hl' => '30 June'],
                    ],
                ],
            ],
            [
                'key' => 'hero',
                'title' => 'Hero Section',
                'content' => [
                    'badge_text' => "India's largest school olympiad",
                    'badge_highlight' => 'NEW · 2026',
                    'headline_line_1' => "Where India's",
                    'headline_highlight' => 'young minds',
                    'headline_line_2' => 'rise to the',
                    'headline_italic' => 'top.',
                    'lede' => 'Compete in national olympiads across 8 subjects, from Class 1 to 12. Earn ranks, medals and scholarships - judged on one fair, transparent national stage.',
                    'primary_cta_label' => 'Start Your Journey',
                    'primary_cta_url' => '/register',
                    'secondary_cta_label' => 'Explore Exams',
                    'secondary_cta_url' => '#exams',
                    'trust_text' => '1,20,000+ students from 5,000+ schools',
                    'rating_text' => 'rated 4.9/5 by parents',
                    'rank_number' => '#01',
                    'rank_label' => 'National Rank',
                    'winner_name' => 'Aarav Mehta',
                    'winner_meta' => 'Class 8 · Maths Olympiad · Delhi',
                    'bars' => [
                        ['label' => 'Accuracy', 'val' => '98%', 'w' => '98%'],
                        ['label' => 'Percentile', 'val' => '99.7', 'w' => '99%'],
                        ['label' => 'Speed Score', 'val' => '92%', 'w' => '92%'],
                    ],
                    'chips' => [
                        ['icon' => 'Target', 'title' => 'Live Ranking', 'subtitle' => 'Updated in real-time', 'color' => 'rgba(22,138,102,.16)'],
                        ['icon' => 'Medal', 'title' => 'Gold Medal', 'subtitle' => 'Certificate + Rs 50K', 'color' => 'rgba(214,153,31,.18)'],
                        ['icon' => 'Certificate', 'title' => 'Verified', 'subtitle' => 'Govt-recognised cert', 'color' => 'rgba(44,73,166,.16)'],
                    ],
                ],
            ],
            [
                'key' => 'stats',
                'title' => 'Stats Strip',
                'content' => [
                    'items' => [
                        ['label' => 'Students Enrolled', 'target' => 120000, 'suffix' => '+'],
                        ['label' => 'Partner Schools', 'target' => 5000, 'suffix' => '+'],
                        ['label' => 'Subjects Offered', 'target' => 8, 'suffix' => ''],
                        ['label' => 'States & UTs', 'target' => 28, 'suffix' => ''],
                    ],
                ],
            ],
            [
                'key' => 'subjects',
                'title' => 'Subjects Section',
                'content' => [
                    'eyebrow' => 'Choose your arena',
                    'title' => 'Eight subjects. One national stage.',
                    'description' => "Every olympiad is crafted by India's top educators and mapped to your class syllabus - challenging enough to stretch, fair enough to enjoy.",
                    'groups' => [
                        [
                            'key' => 'core',
                            'icon' => 'Brain',
                            'label' => 'Core Academics',
                            'subjects' => [
                                ['name' => 'Mathematics', 'icon' => 'Math', 'color' => '#2C49A6', 'range' => 'Class 1-12', 'desc' => 'Logic, numbers and problem-solving from arithmetic to advanced reasoning.'],
                                ['name' => 'Science', 'icon' => 'Science', 'color' => '#168A66', 'range' => 'Class 1-12', 'desc' => 'Physics, chemistry and biology brought alive through concept-first questions.'],
                                ['name' => 'General Knowledge', 'icon' => 'World', 'color' => '#EE6A2C', 'range' => 'Class 1-10', 'desc' => 'Current affairs, history, geography and the world around us.'],
                            ],
                        ],
                        [
                            'key' => 'lang',
                            'icon' => 'Language',
                            'label' => 'Languages',
                            'subjects' => [
                                ['name' => 'English', 'icon' => 'Book', 'color' => '#6C3FA0', 'range' => 'Class 1-12', 'desc' => 'Grammar, comprehension and vocabulary tested the smart way.'],
                                ['name' => 'Hindi', 'icon' => 'Hindi', 'color' => '#C9501A', 'range' => 'Class 1-10', 'desc' => 'Vyakaran, gadya aur kavya - strengthen your mother tongue.'],
                                ['name' => 'Social Science', 'icon' => 'Civics', 'color' => '#168A66', 'range' => 'Class 6-12', 'desc' => 'History, civics and geography for the curious citizen.'],
                            ],
                        ],
                        [
                            'key' => 'future',
                            'icon' => 'Rocket',
                            'label' => 'Future Skills',
                            'subjects' => [
                                ['name' => 'Computers', 'icon' => 'Computer', 'color' => '#2C49A6', 'range' => 'Class 3-12', 'desc' => 'Logic, coding fundamentals and digital literacy for the AI age.'],
                                ['name' => 'Logical Reasoning', 'icon' => 'Puzzle', 'color' => '#D6991F', 'range' => 'Class 1-12', 'desc' => 'Patterns, puzzles and analytical thinking that sharpen the mind.'],
                                ['name' => 'Creativity & Art', 'icon' => 'Art', 'color' => '#EE6A2C', 'range' => 'Class 1-8', 'desc' => 'Visual thinking, design sense and imaginative problem-solving.'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'how_it_works',
                'title' => 'How It Works',
                'content' => [
                    'eyebrow' => 'Simple by design',
                    'title' => 'From sign-up to stardom in three steps',
                    'steps' => [
                        ['icon' => 'Register', 'title' => 'Register & Pick', 'desc' => 'Create a free account, choose your class and the subjects you want to conquer.'],
                        ['icon' => 'Lightning', 'title' => 'Compete Online', 'desc' => 'Take the timed MCQ exam from home in a secure, full-screen proctored room.'],
                        ['icon' => 'Trophy', 'title' => 'Win & Celebrate', 'desc' => 'Get instant scores, national ranks, downloadable certificates and rewards.'],
                    ],
                ],
            ],
            [
                'key' => 'exams',
                'title' => 'Upcoming Exams Section',
                'content' => [
                    'eyebrow' => 'Upcoming olympiads',
                    'title' => 'Live exams open for registration',
                    'view_all_label' => 'View all exams ->',
                    'empty_title' => 'New olympiads are being scheduled',
                    'empty_description' => 'Published exams from Exam Management will appear here automatically.',
                ],
            ],
            [
                'key' => 'rewards',
                'title' => 'Rewards & Medals',
                'content' => [
                    'eyebrow' => 'Recognition that matters',
                    'title' => 'Compete for more than a score',
                    'description' => 'Every winner earns a verified certificate, a national rank badge and real rewards worth celebrating.',
                    'tiers' => [
                        ['key' => 'silver', 'label' => 'Silver Tier', 'headline' => 'Top 10%', 'perks' => ['Digital + printed certificate', 'State-level rank badge', 'Rs 10,000 scholarship']],
                        ['key' => 'gold', 'label' => 'Gold Tier', 'headline' => 'Top 1%', 'perks' => ['Govt-recognised certificate', 'National rank + trophy', 'Rs 50,000 scholarship', 'Felicitation ceremony']],
                        ['key' => 'bronze', 'label' => 'Bronze Tier', 'headline' => 'Top 25%', 'perks' => ['Digital merit certificate', 'School-level rank badge', 'Practice vouchers']],
                    ],
                ],
            ],
            [
                'key' => 'testimonials',
                'title' => 'Testimonials',
                'content' => [
                    'eyebrow' => 'Loved across India',
                    'title' => 'Students and parents on the Hunt',
                    'items' => [
                        ['quote' => 'The exam room felt exactly like a real competition. My son cleared the Maths olympiad with All-India Rank 14 - the certificate is now framed on our wall!', 'name' => 'Rohit Malhotra', 'role' => 'Parent · Bengaluru, Karnataka', 'initials' => 'RM', 'color' => '#2C49A6'],
                        ['quote' => 'I loved how fast the results came. Within minutes I saw my score, my rank and where I went wrong. It pushed me to practice harder for the next round.', 'name' => 'Ananya Sharma', 'role' => 'Class 9 · Jaipur, Rajasthan', 'initials' => 'AS', 'color' => '#168A66'],
                        ['quote' => 'As a school we registered 200 students. The dashboard, the ranks, the certificates - everything was seamless and genuinely motivating for the kids.', 'name' => 'Veena Pillai', 'role' => 'Principal · Kochi, Kerala', 'initials' => 'VP', 'color' => '#EE6A2C'],
                    ],
                ],
            ],
            [
                'key' => 'faq',
                'title' => 'FAQ Section',
                'content' => [
                    'eyebrow' => 'Good to know',
                    'title' => 'Questions, answered.',
                    'aside_title' => 'Still curious?',
                    'aside_description' => 'Our support team replies within a few hours, every day of the week.',
                    'contact_label' => 'Talk to us',
                    'contact_url' => '#contact',
                    'support_email' => 'care@olympiadhunt.in',
                    'support_phone' => '+91 98765 43210 · Mon-Sun',
                    'items' => [
                        ['q' => 'Who can participate in the olympiads?', 'a' => 'Any student from Class 1 to Class 12 in India can register. You only need to pick your current class and the subjects you would like to compete in - no school nomination required.'],
                        ['q' => 'How are the exams conducted?', 'a' => 'All exams are online, timed MCQ tests taken in a secure full-screen room from any laptop or tablet. The system auto-submits when time ends and flags tab-switching to keep it fair for everyone.'],
                        ['q' => 'When will I get my results and rank?', 'a' => 'You will see your score and a detailed breakdown instantly after submitting. National, state and school ranks are published once the exam window closes - usually within 48 hours.'],
                    ],
                ],
            ],
            [
                // Replaces the old "register" CTA/form block. The homepage now renders a
                // Contact Us form here; submissions are captured as leads (admin → Forms).
                'key' => 'contact',
                'title' => 'Contact Us Section',
                'content' => [
                    'eyebrow' => 'Get in touch',
                    'title' => "We'd love to hear from you",
                    'description' => 'Questions about exams, payments or your account? Send us a message and our team will get back to you.',
                    'form_title' => 'Send us a message',
                    'form_description' => 'Fill in the form and we will respond shortly.',
                    'button_label' => 'Send Message',
                    'note' => 'We respect your privacy. Your details are never shared.',
                    'success_title' => 'Message sent!',
                    'success_description' => 'Thanks for reaching out — our team will get back to you within 24 hours.',
                    'details' => [
                        ['icon' => 'Mail', 'title' => 'Email us', 'sub' => 'care@olympiadhunt.in'],
                        ['icon' => 'Phone', 'title' => 'Call us', 'sub' => '+91 98765 43210 · Mon–Sun'],
                        ['icon' => 'Clock', 'title' => 'Response time', 'sub' => 'We reply within 24 hours'],
                    ],
                ],
            ],
            [
                'key' => 'final_cta',
                'title' => 'Final CTA Banner',
                'content' => [
                    'title' => 'Ready to prove your brilliance on the national stage?',
                    'description' => "Join India's fastest-growing olympiad community and turn your hard work into recognition.",
                    'primary_label' => 'Register Free Now',
                    'primary_url' => '/register',
                    'secondary_label' => 'Browse Exams',
                    'secondary_url' => '#exams',
                ],
            ],
            [
                'key' => 'footer',
                'title' => 'Footer',
                'content' => [
                    'description' => "India's premier olympiad platform for school students, building confidence one competition at a time.",
                    'copyright' => 'National Olympiad Hunt. All rights reserved. Made in India',
                    'socials' => [
                        ['label' => 'Instagram', 'url' => '#'],
                        ['label' => 'YouTube', 'url' => '#'],
                        ['label' => 'Twitter', 'url' => '#'],
                        ['label' => 'Facebook', 'url' => '#'],
                    ],
                    'columns' => [
                        ['title' => 'Platform', 'links' => [['label' => 'Subjects', 'url' => '/exams'], ['label' => 'Exams', 'url' => '/exams'], ['label' => 'Rewards', 'url' => '#rewards'], ['label' => 'Leaderboard', 'url' => '/results'], ['label' => 'Practice Tests', 'url' => '/exams']]],
                        ['title' => 'Company', 'links' => [['label' => 'About Us', 'url' => '/about'], ['label' => 'How it Works', 'url' => '#how'], ['label' => 'Blog', 'url' => '/blog'], ['label' => 'Careers', 'url' => '/contact'], ['label' => 'Contact', 'url' => '/contact']]],
                        ['title' => 'Support', 'links' => [['label' => 'FAQ', 'url' => '#faq'], ['label' => 'Help Center', 'url' => '/contact'], ['label' => 'Terms of Use', 'url' => '/terms'], ['label' => 'Privacy Policy', 'url' => '/privacy'], ['label' => 'Refund Policy', 'url' => '/refund']]],
                    ],
                ],
            ],
        ];
    }

    public static function mergeWithDefault(string $key, array $content): array
    {
        $default = self::defaultFor($key);

        // Top-level replace only (NOT recursive): the admin's value for each key —
        // including list arrays like `groups`/`items` — fully replaces the default,
        // so removed list items stay removed. Missing keys still fall back to the
        // default, which backfills any new fields added to defaults over time.
        return array_replace($default['content'] ?? [], array_filter($content, fn ($value) => $value !== null));
    }
}
