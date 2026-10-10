# National Olympiad Hunt

Laravel 12, Inertia.js and Vue 3 application for managing olympiads, student
enrollments, payments, results, certificates, communications and public content.

## Admin Student Reports

The admin Reports page at `/admin/reports` provides composable student filters for:

- Paid, unpaid, pending, failed, refunded and absent payment records
- Subject and olympiad/course
- Student class and state
- A single student registration date range

The page includes filtered summary metrics, sorting, pagination, student profile links,
and genuine XLSX and PDF exports. Page results and both exports share the same validated
`StudentReportService` query.

Export dependencies are locked to the PHP 8.2 application baseline:

- `phpoffice/phpspreadsheet` 5.9
- `dompdf/dompdf` 3.1
- `maennchen/zipstream-php` 3.1.2 (transitive, PHP 8.2 compatible)

Install exactly from the committed lock file:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
```

Reports coverage lives in `tests/Feature/AdminStudentReportTest.php` and currently
contains 8 tests with 179 assertions.

## Question Bank Excel Import

`/admin/questions` → **Download Template** / **Bulk Import**. The template's dropdowns are
filled from live subjects, classes and categories, and row 5 is a demo row that is skipped
unless its question text is replaced. Uploads (max 500 rows) are staged for review — every
row can be corrected or removed — and nothing reaches the Question Bank until the admin
confirms. Coverage: `tests/Feature/AdminQuestionBulkImportTest.php`.

## Exam Builder, Sections and Excel Import

Create/Edit Exam is a four-step builder: **Details → Sections & Questions → Scoring & Rules →
Review & Publish**. Saving the details creates a draft and opens the builder.

- An exam paper is an ordered list of **sections** (e.g. Reasoning → Mathematics → English).
  Each section draws questions from its own bank subject and can override marks and add
  instructions for students.
- Questions are added with **Pick from bank** (search and filter, multi-select),
  **Write new** (saved to the Question Bank and added to the section) or **Import from Excel**,
  and reordered by drag, arrows or "move to position / section".
- **Import from Excel** (exams list, per-exam *Import*, Create Exam → *Save & import from
  Excel*, or the builder): download the exam's template, fill one row per question in paper
  order with its `Section`, optionally reuse an existing question by `Question Bank ID`, then
  review and confirm. Choose *add to the current paper* or *replace the paper*. Nothing is saved
  before confirmation.
- Students see section headers in the exam room; "shuffle questions" shuffles within sections.
- Processing results stores a section-wise breakdown; admins see section averages and each
  student's section scores, and students see a Section-wise Performance panel.

Deploying this release requires the new migrations (`exam_sections`,
`exam_questions.exam_section_id`, `results.section_scores`); existing exams are moved into a
single "General" section automatically:

```bash
php artisan migrate --force
npm run build
```

Coverage: `AdminExamSectionsTest`, `AdminExamQuestionImportTest`, `ExamSectionResultsTest`
and `AdminExamManagementTest` in `tests/Feature/`.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
