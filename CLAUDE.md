<!-- suite-unico:start -->
## La suite Unico (mediatori creditizi)

Questa app fa parte di una suite che condivide il database `core` e la libreria `meo-icar/unico-core`:

- **unicoagent**: preventivi e perfezionamento delle pratiche. App separata che parla con i vari CRM solo via API (gateway in `app/Services/Crm`: unicofinance, Mediafacile, REST generico).
- **unicofinance**: CRM e istruttoria (pratiche, clienti, documenti, agenti, istruttori; pannelli admin e agenti). Espone le API per unicoagent (`/api/agente/v1`, token e firma) e per unicobpm (`/api`, chiave `X-Api-Key`).
- **unicocoge** (ex Proforma): interfaccia COGE e contributi: provvigioni, proforma, fatture, prima nota.
- **unicooam**: compliance e organigramma (Dipendenti con i ruoli in `employee_roles`, documenti e scadenziario, OAM/IVASS, audit). I ruoli della suite si definiscono qui.
- **unicobpm**: BPM e coordinatore. Processi con RACI che chiamano via API le altre app (`config/services.php`, `apps`) e reagiscono a eventi esterni.
- **unico-core**: libreria comune per schema condiviso, documenti, email, firma, accesso e bridge `/api/commands`.
- Fuori da questo contesto, da non toccare: unicogdpr, ShareSync, backups3.

Regole valide in tutte le app:
- Lo schema condiviso si cambia solo in unico-core (migration con `unico-core:migrate`, protette da `hasTable`/`hasColumn`); le app non alterano a mano le tabelle del core. Id sempre interi.
- Istruttore = dipendente con il ruolo `istruttore` in `employee_roles`, come nell'organigramma di unicooam (in unicofinance: `App\Support\Istruttori`).
- Gli stati delle pratiche stanno in `pratica_stati` (flag `is_working`, `is_approved`, `is_rejected`, `is_suspended`...). La vecchia `pratiches_statos` non si usa più.
- Le app si parlano via API: chiavi solo nel `.env` (mai nel codice, nei log o nei commit) e nessuna lettura diretta delle tabelle di un'altra app se esiste un'API. Niente dati personali nei log.
- I database sono di sviluppo. Rispondi e scrivi i testi per gli utenti in italiano.
- Se esiste `graphify-out/GRAPH_REPORT.md`, consultalo prima di cercare nel codice; si aggiorna con `graphify update .`. Per l'estrazione semantica (documenti, PDF, immagini) graphify usa `GEMINI_API_KEY` dal `.env`: non stamparla né committarla.

## Questa app: unicofinance (CRM e istruttoria)
- Lo stato della pratica cambia solo da `App\Services\CambioStatoPratica` (storico e date). `Pratica::inCorso()` è la condizione «in corso» sui flag dello stato.
- Presa in carico: `App\Services\Istruttoria\PresaInCarico`. La pratica perfezionata aspetta in «INVIO IN ISTRUTTORIA»; quando l'istruttore la apre (o preme «Prendi in carico») passa a «PRESA IN CARICO»; se il produttore è anche l'istruttore ci va direttamente. Da lì il produttore non la modifica più (blocco per stato, non per `employee_id`). `is_working` = non concluso.
- L'istruttore si assegna (senza prenderla in carico) da `App\Services\Istruttoria\AssegnaIstruttore` (agente che è anche dipendente, specialista della banca, del prodotto, poi chi ha meno pratiche): specializzazioni in `employees.tipoprodotto_ids` e `clienti_ids`, modificabili da Istruttori.
- API per unicobpm in `routes/api.php`: `/api/pratiche/{id}`, `/api/pratiche/in-corso`, `/api/widgets/{widget}` (le tabelle dei widget, globali o per istruttore/agente: qui non si invia nulla, decide unicobpm), `/api/models/...`.
- I widget della dashboard implementano `EspostoInApi`: la stessa query serve la tabella e l'API.

### Manuali (consultali prima di rispondere)
- Manuale utente: `resources/manuals/manuale-utente.html`. Manuale tecnico: `resources/manuals/manuale-tecnico.html`.
- Ogni sezione ha un `id`: `grep -n 'id="' <file>` dà l'indice, poi leggi la sezione che serve. L'appendice «Fatti dal codice» del manuale tecnico (rotte, comandi, modelli, variabili `.env`) è generata: si rigenera con `python3 /var/www/html/unico-core/tools/manuals/gen_facts.py /var/www/html/unicofinance`; il resto è scritto a mano e va aggiornato quando cambia il comportamento.
- L'assistente dell'istruttore (`App\Neuron\IstruttoreAssistant`) legge entrambi (`ContestoAssistente::manuale()`).
<!-- suite-unico:end -->

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
