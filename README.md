# WordPress Recipe Plugin

A planned WordPress recipe-library plugin for the Marcham Community Fridge website.

The plugin will help visitors find practical recipes for surplus ingredients, then adapt selected recipes to suit what they have available.

## Planned visitor experience

The plugin will provide one Elementor-friendly recipe-library page containing:

- A prominent search bar, such as “What ingredient do you have?”
- Ingredient search with singular/plural matching and synonyms
- Browsing by cuisine
- Optional filters for meal type, dietary preference and preparation time
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

## Recipe data

Recipes will be stored in WordPress as a custom `Recipe` content type. Each recipe should support:

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

## AI recipe modification

AI should modify an approved recipe rather than invent unrestricted food-safety advice. Visitors may request changes such as:

- Use available ingredients
- Replace a missing ingredient
- Change the number of servings
- Make the recipe vegetarian or vegan
- Make the recipe quicker or more suitable for children

AI-generated changes should be clearly labelled and preserve relevant allergen and food-safety warnings. API credentials must remain server-side and must never be exposed in browser JavaScript.

## PDF generation

The PDF action should generate a printable version of the recipe currently shown on screen, including any approved or AI-adapted changes. The online recipe panel must continue to work independently of PDF generation.

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
