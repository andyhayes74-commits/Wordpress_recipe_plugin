(function () {
	'use strict';

	function esc(value) {
		return String(value === undefined || value === null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function list(items, className) {
		if (!Array.isArray(items) || !items.length) {
			return '';
		}
		return '<ul class="' + className + '">' + items.map(function (item) {
			return '<li>' + esc(item) + '</li>';
		}).join('') + '</ul>';
	}

	function labels(items, className) {
		if (!Array.isArray(items) || !items.length) {
			return '';
		}
		return '<div class="' + className + '">' + items.map(function (item) {
			return '<span>' + esc(item) + '</span>';
		}).join('') + '</div>';
	}

	function init(root) {
		var config = window.MCFRecipes || {};
		var configAttribute = root.getAttribute('data-mcf-config');
		if (configAttribute) {
			try {
				config = JSON.parse(configAttribute);
			} catch (error) {
				// Keep the global configuration as a fallback for older cached markup.
			}
		}
		var grid = root.querySelector('[data-mcf-recipe-grid]');
		var detail = root.querySelector('[data-mcf-recipe-detail]');
		var status = root.querySelector('.mcf-recipe-status');
		var searchProgress = root.querySelector('[data-mcf-search-progress]');
		var searchProgressText = root.querySelector('[data-mcf-search-progress-text]');
		var searchProgressDetail = root.querySelector('[data-mcf-search-progress-detail]');
		var form = root.querySelector('.mcf-recipe-search');
		var search = root.querySelector('input[name="search"]');
		var loadMore = root.querySelector('.mcf-recipe-load-more');
		var filters = root.querySelectorAll('[data-mcf-filter]');
		var filterToggles = root.querySelectorAll('[data-mcf-filter-toggle]');
		var state = { page: 1, pages: 1, search: '', searchKey: '', cuisine: '', dietary: '', ai: false };
		var requestSequence = 0;
		var progressTimers = [];
		var otherMatches = document.createElement('details');
		otherMatches.className = 'mcf-recipe-other-matches';
		otherMatches.hidden = true;
		loadMore.insertAdjacentElement('afterend', otherMatches);

		function setStatus(message) {
			status.textContent = message || '';
		}

		function clearProgressTimers() {
			progressTimers.forEach(function (timer) { window.clearTimeout(timer); });
			progressTimers = [];
		}

		function showSearchProgress() {
			if (!searchProgress) { return; }
			clearProgressTimers();
			root.setAttribute('aria-busy', 'true');
			searchProgress.hidden = false;
			if (searchProgressText) {
				searchProgressText.textContent = config.i18n.aiSearching || 'Finding recipes that use your ingredients…';
			}
			if (searchProgressDetail) {
				searchProgressDetail.textContent = config.i18n.aiSearchingDetail || 'Checking the recipes that best fit your search.';
			}
			progressTimers.push(window.setTimeout(function () {
				if (searchProgressDetail) {
					searchProgressDetail.textContent = config.i18n.aiSearchingSlow || 'Still searching carefully for the best matches…';
				}
			}, 2800));
		}

		function hideSearchProgress() {
			clearProgressTimers();
			root.removeAttribute('aria-busy');
			if (searchProgress) {
				searchProgress.hidden = true;
			}
		}

	function endpoint(path, params) {
			var url = String(config.restUrl || '').replace(/\/$/, '') + path;
			var requestParams = Object.assign({}, params || {}, { _mcf_request: Date.now() });
			var query = new URLSearchParams(requestParams);
			return url + (query.toString() ? '?' + query.toString() : '');
		}

		function updateOptions(filtersData) {
			if (!filtersData) {
				return;
			}
			[
				{ name: 'dietary', values: filtersData.dietary || [] }
			].forEach(function (definition) {
				var group = root.querySelector('[data-mcf-filter="' + definition.name + '"]');
				if (!group || group.querySelectorAll('[data-mcf-filter-option]').length > 1) {
					return;
				}
				definition.values.forEach(function (item) {
					var option = document.createElement('button');
					option.type = 'button';
					option.className = 'mcf-recipe-filter-chip';
					option.setAttribute('data-mcf-filter-option', item.slug);
					option.setAttribute('aria-pressed', 'false');
					option.textContent = item.name;
					group.appendChild(option);
				});
				refreshFilterGroup(group, false);
			});
		}

		function refreshFilterGroup(group, expanded) {
			if (!group) {
				return;
			}
			var limit = 5;
			var options = Array.prototype.slice.call(group.querySelectorAll('[data-mcf-filter-option]'));
			var toggle = group.parentNode.querySelector('[data-mcf-filter-toggle]');
			var isExpanded = expanded === true || group.getAttribute('data-expanded') === 'true';
			group.setAttribute('data-expanded', isExpanded ? 'true' : 'false');
			options.forEach(function (option, index) {
				option.hidden = !isExpanded && index >= limit;
			});
			if (toggle) {
				toggle.hidden = options.length <= limit;
				toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
				var moreLabel = config.i18n.showMoreFilters || 'Show more';
				toggle.textContent = isExpanded ? (config.i18n.showLessFilters || 'Show less') : moreLabel + ' (' + (options.length - limit) + ')';
			}
		}

		function setFilterButton(group, value) {
			if (!group) {
				return;
			}
			Array.prototype.forEach.call(group.querySelectorAll('[data-mcf-filter-option]'), function (button) {
				var active = button.getAttribute('data-mcf-filter-option') === value;
				button.classList.toggle('is-active', active);
				button.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
		}

		function card(recipe) {
			var image = recipe.image
				? '<img src="' + esc(recipe.image) + '" alt="' + esc(recipe.image_alt || recipe.title) + '">'
				: '<div class="mcf-recipe-card__placeholder" aria-hidden="true">🍲</div>';
			var meta = [];
			if (recipe.cook_time) {
				meta.push('<span>' + esc(recipe.cook_time) + '</span>');
			}
			if (recipe.servings) {
				meta.push('<span>' + esc(recipe.servings) + '</span>');
			}
			return '<article class="mcf-recipe-card">' +
				'<div class="mcf-recipe-card__image">' + image + '</div>' +
				'<div class="mcf-recipe-card__body">' +
				'<p class="mcf-recipe-card__cuisine">' + esc((recipe.cuisine || []).join(' · ')) + '</p>' +
				'<h3>' + esc(recipe.title) + '</h3>' +
				(recipe.description ? '<p class="mcf-recipe-card__description">' + esc(recipe.description) + '</p>' : '') +
				labels(recipe.dietary, 'mcf-recipe-card__labels') +
				'<div class="mcf-recipe-card__meta">' + meta.join('') + '</div>' +
				'<button type="button" class="mcf-recipe-button" data-mcf-view="' + esc(recipe.id) + '">' + esc(config.i18n.viewRecipe) + ' <span aria-hidden="true">→</span></button>' +
				'</div></article>';
		}

		function trackClick(id, title) {
			if (!id) {
				return;
			}
			fetch(endpoint('/recipes/' + encodeURIComponent(id) + '/click'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({ query_key: state.searchKey || '', title: title || '' })
			}).catch(function () {
				// Popularity tracking is best effort and must never block the recipe.
			});
		}

		function load(reset) {
			var sequence = ++requestSequence;
			var isSearch = Boolean(state.search);
			if (reset) {
				state.page = 1;
				grid.innerHTML = '';
				otherMatches.innerHTML = '';
				otherMatches.hidden = true;
				otherMatches.open = false;
			}
			loadMore.disabled = true;
			setStatus(config.i18n.loading);
			if (isSearch) {
				showSearchProgress();
			} else {
				hideSearchProgress();
			}
			var params = {
				page: state.page,
				per_page: config.perPage || 8
			};
			// Do not send empty filters. Some WordPress/LiteSpeed combinations
			// handle blank REST query values inconsistently.
			if (state.search) {
				params.search = state.search;
				params.ai = state.ai ? '1' : '0';
			}
			if (state.cuisine) {
				params.cuisine = state.cuisine;
			}
			if (state.dietary) {
				params.dietary = state.dietary;
			}
			fetch(endpoint('/recipes', params), { cache: 'no-store', headers: { Accept: 'application/json' } })
				.then(function (response) {
					if (!response.ok) {
						throw new Error('search_failed');
					}
					return response.json();
				})
				.then(function (data) {
					if (sequence !== requestSequence) { return; }
					hideSearchProgress();
					updateOptions(data.filters);
					state.searchKey = data.search_key || '';
					state.pages = Number(data.pages || 1);
					grid.insertAdjacentHTML('beforeend', (data.recipes || []).map(card).join(''));
					loadMore.hidden = state.page >= state.pages;
					loadMore.disabled = false;
					var message = data.total ? data.total + ' ' + (data.total === 1 ? (config.i18n.resultsSingular || 'recipe found') : (config.i18n.resultsPlural || 'recipes found')) : config.i18n.noResults;
					if (data.search_status === 'no_strong_matches') {
						message = config.i18n.noFocusedResults || 'No recipes focused on your search ingredients were found.';
					} else if (data.search_status === 'ai_error') {
						message = config.i18n.aiSearchError || 'The recipe search could not complete. Please try again.';
					} else if (data.search_status === 'local') {
						message = (config.i18n.localSearchNotice || 'Searching recipe titles and main ingredients.') + ' ' + message;
					} else if (data.search_status === 'ai_not_configured') {
						message = config.i18n.aiNotConfigured || 'AI recipe matching is not configured yet.';
					} else if (data.search_status === 'mealdb_error') {
						message = config.i18n.mealdbError || 'The recipe service could not be reached. Please try again.';
					}
					setStatus(message);
					if (state.page === 1 && Array.isArray(data.other_recipes) && data.other_recipes.length) {
						otherMatches.innerHTML = '<summary>' + esc(config.i18n.otherMatches || 'Other recipes containing your ingredients') + ' (' + data.other_recipes.length + ')</summary><div class="mcf-recipe-grid">' + data.other_recipes.map(card).join('') + '</div>';
						otherMatches.hidden = false;
					}
				})
				.catch(function () {
					if (sequence !== requestSequence) { return; }
					hideSearchProgress();
					loadMore.disabled = false;
					loadMore.hidden = true;
					setStatus(config.i18n.searchError || 'Recipes could not be loaded. Please try again.');
				});
		}

		function detailHtml(recipe) {
			var image = recipe.image
				? '<img src="' + esc(recipe.image) + '" alt="' + esc(recipe.image_alt || recipe.title) + '">'
				: '';
			var metadata = [];
			if (recipe.cuisine && recipe.cuisine.length) {
				metadata.push(esc(recipe.cuisine.join(' · ')));
			}
			if (recipe.prep_time) {
				metadata.push(esc(config.i18n.prepLabel || 'Prep:') + ' ' + esc(recipe.prep_time));
			}
			if (recipe.cook_time) {
				metadata.push(esc(config.i18n.cookLabel || 'Cook:') + ' ' + esc(recipe.cook_time));
			}
			if (recipe.servings) {
				metadata.push(esc(config.i18n.servingsLabel || 'Servings:') + ' ' + esc(recipe.servings));
			}
			var sourceLabel = recipe.source_is_original
				? (config.i18n.sourceRecipe || 'View original recipe')
				: (recipe.provider === 'spoonacular'
					? (config.i18n.spoonacularRecipe || 'View on Spoonacular')
					: (config.i18n.mealdbRecipe || 'View on TheMealDB'));
			return '<div class="mcf-recipe-detail__toolbar">' +
				'<button type="button" class="mcf-recipe-link" data-mcf-close>← ' + esc(config.i18n.backToRecipes || 'Back to recipes') + '</button>' +
				'<span class="mcf-recipe-detail__badge">' + esc(recipe.ai_adapted ? (config.i18n.aiAdaptedBadge || 'AI-adapted') : (config.i18n.recipeBadge || 'Recipe')) + '</span>' +
				'</div>' +
				'<div class="mcf-recipe-detail__content">' +
				(image ? '<div class="mcf-recipe-detail__image"><span class="mcf-recipe-detail__selected">✓ Selected</span>' + image + '</div>' : '') +
				'<div class="mcf-recipe-detail__copy">' +
				'<h2>' + esc(recipe.title) + '</h2>' +
				'<div class="mcf-recipe-detail__meta">' + metadata.join(' · ') + '</div>' +
				'<p>' + esc(recipe.description || '') + '</p>' +
				'<h3>' + esc(config.i18n.ingredientsHeading || 'Ingredients') + '</h3>' + list(recipe.ingredients, 'mcf-recipe-list') +
				'<h3>' + esc(config.i18n.methodHeading || 'Method') + '</h3>' + list(recipe.method, 'mcf-recipe-steps') +
				(recipe.allergens ? '<div class="mcf-recipe-callout"><strong>' + esc(config.i18n.allergenHeading || 'Allergen information') + '</strong><br>' + esc(recipe.allergens) + '</div>' : '') +
				(recipe.storage ? '<div class="mcf-recipe-callout"><strong>' + esc(config.i18n.storageHeading || 'Storage and reheating') + '</strong><br>' + esc(recipe.storage) + '</div>' : '') +
				(recipe.warnings && recipe.warnings.length ? '<div class="mcf-recipe-callout mcf-recipe-callout--warning"><strong>' + esc(config.i18n.adaptationNotesHeading || 'AI adaptation notes') + '</strong>' + list(recipe.warnings, 'mcf-recipe-list') + '</div>' : '') +
				'<div class="mcf-recipe-detail__actions">' +
				'<a class="mcf-recipe-button mcf-recipe-button--green" href="' + esc(recipe.source_url || '') + '" target="_blank" rel="noopener noreferrer">' + esc(sourceLabel) + ' ↗</a>' +
				'<button type="button" class="mcf-recipe-button mcf-recipe-button--outline" data-mcf-download-pdf>' + esc(config.i18n.downloadPdf || 'Download recipe PDF') + '</button>' +
				'</div></div></div>';
		}

		function openRecipe(id) {
			setStatus(config.i18n.loading);
				fetch(endpoint('/recipes/' + encodeURIComponent(id)), { cache: 'no-store', headers: { Accept: 'application/json' } })
				.then(function (response) {
					if (!response.ok) {
						throw new Error('recipe_failed');
					}
					return response.json();
				})
				.then(function (recipe) {
					trackClick(id, recipe.title);
					detail.dataset.recipeId = id;
					detail.innerHTML = detailHtml(recipe);
					detail.hidden = false;
					root.classList.add('mcf-recipe-library--detail-open');
					window.requestAnimationFrame(function () {
						// Focusing the panel keeps keyboard and screen-reader users oriented.
						// Do it before the explicit scroll: browser focus scrolling may otherwise
						// place a long recipe too far down the mobile viewport.
						try {
							detail.focus({ preventScroll: true });
						} catch (error) {
							detail.focus();
						}

						var offset = window.innerWidth <= 640 ? 16 : 24;
						var currentTop = window.pageYOffset || window.scrollY || 0;
						var targetTop = currentTop + detail.getBoundingClientRect().top - offset;
						window.scrollTo({ top: Math.max(0, targetTop), behavior: 'auto' });
					});
					setStatus('');
				})
				.catch(function () {
					setStatus(config.i18n.noResults);
				});
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			state.search = search.value.trim();
			state.ai = Boolean(state.search);
			load(true);
		});
		Array.prototype.forEach.call(filters, function (filter) {
			filter.addEventListener('click', function (event) {
				var button = event.target.closest('[data-mcf-filter-option]');
				if (!button) {
					return;
				}
				var value = button.getAttribute('data-mcf-filter-option') || '';
				state[filter.getAttribute('data-mcf-filter')] = value;
				state.ai = Boolean(state.search);
				setFilterButton(filter, value);
				load(true);
			});
		});
		Array.prototype.forEach.call(filterToggles, function (toggle) {
			toggle.addEventListener('click', function () {
				var group = toggle.parentNode.querySelector('[data-mcf-filter]');
				refreshFilterGroup(group, toggle.getAttribute('aria-expanded') !== 'true');
			});
		});
		loadMore.addEventListener('click', function () {
			state.page += 1;
			load(false);
		});
		root.addEventListener('click', function (event) {
			var view = event.target.closest('[data-mcf-view]');
			if (view) {
				openRecipe(view.getAttribute('data-mcf-view'));
				return;
			}
			if (event.target.closest('[data-mcf-close]')) {
				detail.hidden = true;
				root.classList.remove('mcf-recipe-library--detail-open');
				return;
			}
			if (event.target.closest('[data-mcf-download-pdf]')) {
				var recipeId = detail.dataset.recipeId || '';
				if (recipeId && config.pdfUrl) {
					window.location.assign(config.pdfUrl + '&id=' + encodeURIComponent(recipeId));
				}
				return;
			}
		});
		root.addEventListener('mcf:open', function (event) {
			if (event.detail && event.detail.id) {
				detail.dataset.recipeId = event.detail.id;
			}
		});
		load(true);
	}

	function initAll() {
		Array.prototype.forEach.call(document.querySelectorAll('.mcf-recipe-library'), function (root) {
			if (root.getAttribute('data-mcf-initialised') === '1') {
				return;
			}
			root.setAttribute('data-mcf-initialised', '1');
			init(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
	// LiteSpeed may inject delayed scripts after DOMContentLoaded has fired.
	document.addEventListener('DOMContentLoadedLiteSpeedLoaded', initAll);
}());
