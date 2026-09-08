# WordPress Recipe Plugin

A WordPress recipe-library plugin for the Marcham Community Fridge website.

The plugin helps visitors find practical recipes for surplus ingredients from switchable recipe providers and links visitors to the original source where one is available.

## Release status

| Version | Branch | Status |
| --- | --- | --- |
| v1.3.12 | `v1.3.12-vegetable-loader` | Current release candidate. Replaces the generic loading spinner with an upright vegetable orbit. |
| v1.3.11 | `v1.3.11-dietary-filters` | Current release candidate. Replaces cuisine browsing with provider-backed dietary filters. |
| v1.3.10 | `v1.3.10-spoonacular-full-metric` | Current release candidate. Converts remaining US volume, weight and oven-temperature units. |
| v1.3.9 | `v1.3.9-spoonacular-metric` | Earlier metric-data candidate. Requests and displays metric Spoonacular ingredient measures. |
| v1.3.8 | `v1.3.8-spoonacular-providers` | Earlier provider-controls candidate. Adds secure Spoonacular integration and provider enable/disable switches. |
| v1.3.7 | `v1.3.7-recipe-source-links` | Earlier source-link candidate. Replaces visitor AI modification with verified source links. |
| v1.3.6 | `v1.3.6-mobile-detail-scroll` | Current release candidate. Aligns the selected recipe panel at the top of the viewport on mobile. |
| v1.3.5 | `v1.3.5-popular-browse` | Current release candidate. Sorts the initial browse page by real visitor popularity, with a varied fallback. |
| v1.3.4 | `v1.3.4-search-normalisation` | Earlier search-normalisation candidate. |
| v1.3.3 | `v1.3.3-mealdb-reliability` | Earlier MealDB reliability candidate. |
| v1.3.2 | `v1.3.2-title-first-matching` | Earlier title-first matching candidate. |
| v1.3.1 | `main` | Previous baseline release. |

For installation, use the purpose-built [marcham-recipe-plugin-v1.3.12.zip](releases/marcham-recipe-plugin-v1.3.12.zip) release package. Do **not** use GitHub’s **Code → Download ZIP** archive: it uses the repository/branch folder name and WordPress may treat it as a different plugin rather than an update.


## Current MVP

The first working version includes:

- WordPress Recipe content type with recipe metadata and searchable taxonomies
- Admin recipe editing with draft, pending-review and published states
- CSV import with optional image URL download into the Media Library
- Elementor-compatible [mcf_recipes] shortcode
- Searchable TheMealDB or Spoonacular recipe cards with dietary filters
- In-page recipe detail panel
- Original-recipe source link, with a provider page fallback
- Browser print / save-as-PDF output for the selected recipe
- Settings-based typography, colours, spacing and corner-radius controls
- Editable visitor-facing wording for the complete recipe-library interface
- REST responses marked as non-cacheable so published/imported recipes appear without waiting for LiteSpeed cache expiry
- Relative REST URL handling so recipe searches work when the page is served over HTTPS
- Initialisation support for LiteSpeed delayed JavaScript loading
- Search requests use recipe titles and curated search terms, not incidental ingredients
- Duplicate-safe CSV imports that update matching recipe titles instead of creating accidental copies
- CSV validation and an import report showing created, updated, skipped, failed and image-download rows
- Numbered single-line method fields are split into readable ordered steps when imported or displayed
- LiteSpeed JS-delay exclusions for the recipe interface while allowing the surrounding page to remain cached
- Marcham concept visual treatment with a warm cream canvas, green/orange hierarchy, rounded cards and leaf decoration
- Browse-by-dietary chip controls that work well on touch screens and keyboard navigation
- Two-column recipe-card presentation on larger screens with a compact, single-column mobile layout
- Selected-recipe panel styled as an in-page recipe feature with a prominent image, selected badge and action buttons
- Dietary choices shown with the first five options visible and the rest expandable
- Relevance-ranked ingredient search using the active provider's candidates, ingredient meaning, recipe titles and method text rather than every incidental word
- Multi-term ingredient searches use an AND-style match, so “carrot beef” favours recipes matching both terms
- Dynamic, server-side OpenAI relevance search with persistent, reversible learned decisions
- Search progress feedback while AI is checking recipes, including a slower-search message
- Learned-search administration screen and opt-in diagnostics for investigating relevance and timing
- Switchable public recipe providers, with local learning records keyed to provider-specific recipe IDs
- Suitability-aware AI ranking for surplus ingredients, including stricter multi-ingredient matching
- Query-scoped and global recipe click tracking, with popularity used only inside AI-approved result groups
- Enable/disable switches for TheMealDB and Spoonacular, with server-only API-key fields
- Deterministic primary/secondary/incidental match bands remove noisy candidates before AI ranking
- AI-selected strong results are constrained to deterministic primary matches; weaker selections are demoted or discarded
- Diagnostics show each candidate's match band, score and matched terms

This is an MVP. Recipes should be tested with a small CSV first and reviewed for allergens, storage advice and cooking instructions before publication.

## Updating the existing plugin

This release keeps the same WordPress plugin identity as v0.1.0: the main file remains `marcham-recipe-plugin.php`, the plugin name remains **Marcham Community Fridge Recipe Library**, and the text domain remains `marcham-recipe-plugin`. The install ZIP also uses the stable `marcham-recipe-plugin/` folder, which is required for WordPress to recognise it as an update to the existing installation.

Back up the WordPress files and database first. In **Plugins → Add New Plugin → Upload Plugin**, upload `marcham-recipe-plugin-v1.3.12.zip` and choose **Replace current with uploaded** if WordPress presents that option. Do not use GitHub's **Code → Download ZIP** archive directly; use the plugin ZIP built for release. If WordPress offers only a new installation or reports that the destination already exists, cancel and do not activate a duplicate copy. Existing local recipes, settings and the settings-based OpenAI key are preserved. Purge the recipe page's LiteSpeed cache once after updating to load the new script/configuration.

## v1.3.12: Vegetable loading animation

- The generic circular spinner is replaced with small carrot, broccoli and pepper illustrations in the Marcham palette.
- The vegetables orbit as a group while counter-rotating individually, keeping each illustration vertically upright throughout the animation.
- Visitors who prefer reduced motion see the same illustrations without animation.

## v1.3.11: Dietary filters

- The public filter section now offers dietary options instead of cuisines.
- Spoonacular offers Vegetarian, Vegan, Gluten-free, Dairy-free, Low FODMAP, Ketogenic and Whole30. Gluten-free and Dairy-free use Spoonacular's intolerance filtering; the remaining options use its diet filters.
- TheMealDB offers Vegetarian only, because its other dietary classifications are not reliable enough to show as filters.
- Recipe cards continue to show a recipe's cuisine in the card, while Spoonacular dietary labels are also displayed where available.

## v1.3.10: Complete UK metric display

- Teaspoons, tablespoons, cups and fluid ounces are converted to millilitres; ounces and pounds are converted to grams.
- Fahrenheit oven temperatures are converted to Celsius, including bare US-style instructions such as `425 degrees`.
- Ingredient counts remain counts, but unusable provider units such as `servings bell pepper` are displayed as a simple count of the ingredient instead.

## v1.3.9: Metric Spoonacular ingredients

- Spoonacular requests now include `units=metric`.
- The recipe panel builds ingredient lines from Spoonacular's dedicated metric amount and unit fields, rather than its original US-focused ingredient wording.
- The Spoonacular cache key is versioned, so previously cached imperial responses are not reused after the update.

## v1.3.8: Spoonacular provider and provider controls

- **Recipe Library → Settings → Recipe providers** now includes switches for TheMealDB and Spoonacular.
- Spoonacular is disabled by default, so updating does not unexpectedly consume its API quota. Paste its API key into the password field and enable its switch to use it.
- When both are enabled, Spoonacular is selected as the active provider because it can return richer recipe descriptions, ingredient lists and analysed instructions. Turn it off to return to TheMealDB immediately. If Spoonacular is enabled without a saved key, enabled TheMealDB remains the fallback.
- Provider-specific IDs such as `mealdb:52965` and `spoonacular:716429` keep click popularity, saved AI decisions and diagnostics separate even if services reuse the same number.
- The provider API key is used only from WordPress server requests, never exposed in the browser. No Spoonacular recipe content is copied into the learning tables.

## v1.3.7: Recipe source links

- The visitor-facing “Modify with AI” action and its REST endpoint have been removed.
- Every recipe now has a source button. It opens the original publisher URL when TheMealDB provides one, or its matching TheMealDB recipe page when it does not.
- OpenAI continues to assess search suitability; it is no longer used to rewrite recipes live for visitors.

## v1.3.6: Reliable selected-recipe position on mobile

- Opening a recipe now explicitly aligns the top of its detail panel just below the mobile browser header.
- Keyboard and screen-reader focus is retained without allowing browser focus handling to choose an unhelpful scroll position for a long recipe.

## v1.3.5: Popular browse page

- Opening the recipe page now shows the most-clicked MealDB recipes first, using only the existing global click records.
- Recipes with no clicks do not disappear: the rest of the 24-card browse page is filled from a varied Vegetarian, Beef and Chicken fallback selection.
- The search page continues to use query-specific popularity only after suitability matching, so popular recipes cannot distort an ingredient search.
- The filter control shows the number of additional dietary options—not recipes.

## v1.3.4: Search normalisation and complete title matches

- Singular and plural ingredient forms now share one search term: `potato` and `potatoes`, for example, are evaluated together.
- Title-led dishes remain primary even when the ingredient appears after up to three descriptive words, such as *Spicy North African Potato Salad*.
- The AI can still rank matches, but it can no longer hide clear title-led primary matches. These remain in the main results instead of being demoted to “other recipes”.

## v1.3.3: MealDB reliability and fallback

- Title-search responses are used directly during candidate scoring, avoiding repeat lookups for those recipes. Ingredient-filter-only candidates are limited to 18, which reduces the first uncached-search delay.
- A title-led recipe is strongly favoured. A meal that merely includes one carrot alongside several other ingredients is a secondary suggestion, not a primary carrot recipe.
- If OpenAI matching is unavailable, rate-limited or temporarily fails, the plugin returns its deterministic primary and secondary MealDB matches instead of an empty result set.
- The matching policy version is bumped so earlier learned decisions are refreshed automatically.

## v1.3.2: title-first MealDB matching

- MealDB title searches are merged with ingredient-filter results before candidates are scored. This brings title-led meals such as Moroccan Carrot Soup into the shortlist even when the ingredient filter uses different wording.
- Title candidates are considered before the wider ingredient list, so obvious carrot-led meals are not crowded out by incidental matches.
- A term near the start of a short dish title is treated as title-led. A late side item in a longer title is only a weaker candidate unless the ingredient proportion and method also support it.
- Multi-ingredient searches remain AND searches: every strong result must still be a primary match for every requested ingredient.

## v1.3.1: stricter primary-ingredient matching

- TheMealDB candidates are scored before AI using title presence, the proportion of ingredient lines, quantities and repeated use in the method.
- Candidates are marked `primary`, `secondary` or `incidental`. Incidental candidates are removed before the AI request.
- AI strong results must be deterministic `primary` matches. AI attempts to promote secondary matches are demoted to the weaker group, and incidental IDs are discarded.
- The policy version is bumped so previous broad learned decisions are not reused.

For a potato search, this keeps potato soup, jacket potatoes, roast potatoes, Bubble & Squeak and potato salad eligible while demoting recipes where potato is only incidental. The score is a safety and ranking signal, not a substitute for human review.

## v1.3.0: TheMealDB source, suitability ranking and popularity learning

- Public browse and search results come from TheMealDB through server-side REST requests. Existing local WordPress recipes remain installed for rollback but are not the public source on this branch.
- The OpenAI key already stored in Recipe Library settings is reused. TheMealDB has its own optional key setting; blank uses development key `1`.
- A single-ingredient search favours recipes centred on that ingredient. A cottage pie containing a little carrot should not outrank carrot soup for `carrot`.
- A multi-ingredient search requires every requested ingredient to be meaningfully used. `carrot beef` must not return carrot soup.
- Candidate sets and valid AI decisions are fingerprinted and cached locally. The learning tables reference TheMealDB IDs rather than copying recipe content.
- Opening a recipe records a query-specific and global click count. Popularity only breaks ties within the AI-approved strong/other groups, so an unsuitable popular recipe cannot be promoted.
- Search diagnostics now display TheMealDB candidate and selected titles, while Search learning displays most-clicked meals.

The public page continues to show recipe details in the browser, with an original-source link and print/save-as-PDF. Spoonacular dietary filters are provider-backed; TheMealDB only exposes its Vegetarian category, so no broader MealDB allergy or diet claim is made.

## v1.2.0: learned AI search and visible progress

- Search results are saved in a dedicated WordPress database table as a **learned decision** for the complete search intent. A repeat search reuses it without calling OpenAI.
- Learned decisions keep their own record of the AI interpretation, strong recipe IDs and weaker recipe IDs. They never edit the CSV's editorial `search_terms` or recipe ingredients.
- A recipe's candidate data is fingerprinted. When a new or edited recipe becomes relevant to a search, that search's learned decision is automatically refreshed on its next use. Unrelated decisions remain reusable.
- Multi-ingredient searches are treated as a complete set: `carrots and beef` must use both meaningfully. A carrot soup cannot be returned as either a strong or weaker result for that search.
- The AI receives at most 30 local candidates, not the full library. It corrects minor spelling mistakes and returns only validated candidate IDs.
- With a configured key, a failed AI search falls back to deterministic primary and secondary results. Without a configured key, the plugin uses the same deterministic result set and clearly labels it as a local search.
- The recipe page shows a search-in-progress panel while a search is running, and changes its text if the lookup takes longer than a few seconds. All public text is editable under Settings → Search relevance messages.
- Administrators can review learned decisions in **Recipe Library → Search learning** and clear them all if needed. This is separate from the existing, opt-in **Search diagnostics** log.

The AI cannot find missing recipes or candidates excluded by TheMealDB ingredient/cuisine candidate matching. TheMealDB source data still needs human review for suitability, allergens and food safety. AI relevance is not guaranteed.

### Run your own search checks

1. Open **Recipe Library → Settings**, tick **Search debug logging**, and save.
2. Search the public recipe page for `carrot`, `carrots`, `carrot beef`, and an ingredient absent from your library. Keep dietary on All for the first checks.
3. Open **Recipe Library → Search diagnostics** and refresh it after searching. Expand each row to inspect the search, configured model, source, reason, duration, HTTP status, candidate count and candidate/selected recipe IDs and titles.
4. Repeat an identical search: it should say `learned` if the first AI decision succeeded. A valid empty decision is also learned. Add or edit a candidate recipe and repeat the affected search to check that it receives a fresh AI decision.
5. Turn logging off after testing. Use **Clear debug log** to remove retained entries immediately.

Debug logging is off by default. When enabled it records **visitor search text** (limited to 200 bytes with API-key redaction); do not enter personal information during tests. Only administrators with `manage_options` can view or clear logs. The latest 50 entries are kept for up to 24 hours; no API keys, IP addresses, request headers or raw API responses are stored. Logs are best-effort, not an audit trail: simultaneous requests may overwrite an entry. WordPress transient expiry controls retention; expired records are not displayed.

| Source / reason | Meaning |
| --- | --- |
| `ai / matched` | Fresh AI decision with strong matches |
| `ai / no_strong_matches` | AI found no strong matches; optional weaker matches remain separate |
| `learned` | A previous valid AI decision, including empty results |
| `ai_not_configured / missing_key` | The OpenAI key is missing, so suitability ranking is not run |
| `mealdb_error / ...` | The external recipe source could not be reached or returned an invalid response |
| `learned / matched` | A stored suitability decision was reused without another AI request |
| `ai_error / api_http_error` | Check HTTP status: e.g. 401 authentication, 429 rate/quota, 5xx service error |
| `ai_error / transport_error` | Request failed before a usable HTTP response (such as timeout/network failure) |
| `ai_error / invalid_response`, `invalid_recipe_ids`, `incomplete_response` | AI output could not be used safely |
| `ai_error / rate_limited` | An uncached request arrived within the three-second per-IP window |

### Developer verification

Run `node tests/search-ui.test.cjs` and `node --check assets/js/mcf-recipes.js` for front-end regression/syntax checks. Run `php tests/search-ranking.php` in a PHP CLI environment for mocked ranking, response-validation, rate-limit and privacy checks. These tests make no real OpenAI calls and do not prove live WordPress integration or recipe quality. Lint the plugin PHP files with `php -l` before production deployment.

Build-environment verification for this release: JavaScript regression/syntax checks passed. PHP and live OpenAI tests were **not run** because PHP is unavailable in the build environment. Test on a staging site first, including settings save, administrator-only log access, clear-log nonce protection, filters, pagination, in-page recipe view and PDF printing.

## Installation and setup

1. Install the plugin ZIP through WordPress → Plugins → Add New → Upload Plugin.
2. Activate Marcham Community Fridge Recipe Library.
3. Open Recipe Library → Settings.
4. Leave the existing OpenAI API key in place, or enter a replacement key, and choose the model. The default model is `gpt-4o-mini`. The key is stored in WordPress settings and is used only server-side; it is never sent to the browser.
5. Under **Recipe providers**, keep **Enable TheMealDB** switched on for a no-key fallback. Optionally enter a TheMealDB supporter key; blank uses development key `1`.
6. To use Spoonacular, paste its API key into **Spoonacular API key** and switch on **Enable Spoonacular**. With both switches on, Spoonacular is preferred; switch it off to roll back to TheMealDB. Do not put either provider key in Elementor, JavaScript or a public repository.
7. Keep existing local recipes until the external-provider version has passed staging checks. The CSV importer remains available for rollback/editorial use, but it does not populate public provider results.
8. Add [mcf_recipes] to an Elementor Shortcode widget.

Open **Recipe Library → Settings** to change the public wording, font family, text sizes, weights, alignment, colours, backgrounds and corner radius. These settings apply to every `[mcf_recipes]` shortcode on the site.

AI-assisted suitability ranking remains unavailable until an administrator enters an OpenAI API key. The key must never be placed in an Elementor page, JavaScript file or public repository. The page reports a clear configuration message when the key is missing.

## Visitor experience

The plugin will provide one Elementor-friendly recipe-library page containing:

- A prominent search bar, such as “What ingredient do you have?”
- Ingredient search with singular/plural matching and synonyms
- Browsing by cuisine
- Browse-by-cuisine filtering
- Branded, responsive recipe cards below the search controls
- A “Load more recipes” control so the page does not display hundreds of recipes at once
- An in-page recipe panel or accessible modal when a recipe is selected
- AI modification of the selected recipe
- Print and “Download as PDF” actions

The recipes will remain viewable as normal web content. PDF download will be an optional action, not the only way to read a recipe.

## Recipe cards

Each card should be suitable for mobile and display:

- Recipe image with meaningful alternative text
- Recipe title
- Short description
- Cuisine
- Preparation and cooking time
- Dietary labels
- A clear “View recipe” button

The visual style should match the Marcham Community Fridge identity: orange, dark green and cream, with clear contrast and accessible controls.

## Legacy local recipe data

The rollback/editorial store remains in WordPress as a custom `Recipe` content type. It supports:

- Title and description
- Featured image and image alt text
- Ingredients, quantities and units
- Searchable ingredient tags and synonyms
- Method steps
- Cuisine and meal type
- Dietary tags
- Allergen information
- Preparation time, cooking time and servings
- Storage and reheating advice
- Draft, pending-review and published statuses

The public page will be inserted into Elementor with a shortcode such as:

```text
[mcf_recipes]
```

## Bulk import

The plugin should support CSV import for large recipe collections. A CSV may contain fields such as:

```text
title,description,cuisine,meal_type,ingredients,method,prep_time,cook_time,servings,dietary_tags,allergens,storage_advice,search_terms,image_url,image_alt_text
```

During import, the plugin should be able to:

1. Create each recipe record.
2. Convert ingredient and category values into searchable tags.
3. Download images from stable, authorised URLs into the WordPress Media Library.
4. Set featured images and alt text.
5. Import recipes as drafts or pending review before publication.

The importer now updates an existing recipe when the CSV title matches, with that option enabled by default. This makes it safe to correct and re-import the CSV without creating a second copy of every recipe. New recipes default to draft; an existing recipe keeps its current status unless the CSV contains a valid `status` value. The import results page reports created, updated, skipped, failed and image-download failures. Repeated titles in one CSV are skipped with a warning.

`search_terms` should contain the ingredients or dish types that define a recipe, not every ingredient in it. For example, a carrot-and-lentil soup might use `carrot, lentil, soup`, while a beef lasagne containing a small amount of carrot should not use `carrot` as a main search term. The public search ranks exact title and main-term matches and requires all meaningful words in a multi-term search to match.

When an API key is configured, submitting a search asks OpenAI to rank only the deterministic shortlist. The complete database is not sent on every search. The request contains candidate IDs, titles, descriptions, curated main search terms and ingredients. Valid decisions are saved in the learned-search table and reused until the affected candidate set changes. The local deterministic ranking is used only when no API key is configured; an AI failure is shown to the visitor instead of being silently substituted.

An example file is included at examples/recipes-example.csv. The ingredients and method fields can use `||` or semicolons between entries so the importer can preserve each ingredient and method step separately.

## Recipe source and AI use

The recipe panel links to its original publisher whenever the active provider supplies a source URL. Otherwise it links to that provider's recipe page. OpenAI is used only to rank a deterministic shortlist for the visitor's surplus-food search; it does not modify the visitor-facing recipe text.

## PDF generation

The PDF action should generate a printable version of the recipe currently shown on screen. The online recipe panel must continue to work independently of PDF generation.

## Technical requirements

- WordPress plugin architecture
- Elementor-compatible shortcode output
- Responsive mobile-first interface
- AJAX or REST-powered search without full-page reloads
- Accessible keyboard and screen-reader controls
- Pagination or “Load more” support
- Nonce and capability checks for admin actions
- Sanitisation and escaping of imported and user-provided content
- Clear handling of failed AI, image-import and PDF requests
- Caching that does not serve stale search or AI results

## Content and safety

Recipes should only be imported from sources that may legally be used. Before publication, recipes should be reviewed for ingredient accuracy, allergens, storage guidance and cooking instructions. AI output should not replace that review.

## Development approach

1. Build the recipe content type and admin fields.
2. Add CSV import and test with approximately 20 recipes.
3. Build the searchable recipe-card interface and Elementor shortcode.
4. Add in-page recipe viewing and PDF generation.
5. Add AI modification behind a server-side API endpoint.
6. Test mobile layout, accessibility, import errors and safety review workflow.
7. Import the larger recipe collection in controlled batches.
