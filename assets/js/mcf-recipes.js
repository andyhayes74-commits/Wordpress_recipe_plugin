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
		var grid = root.querySelector('[data-mcf-recipe-grid]');
		var detail = root.querySelector('[data-mcf-recipe-detail]');
		var status = root.querySelector('.mcf-recipe-status');
		var form = root.querySelector('.mcf-recipe-search');
		var search = root.querySelector('input[name="search"]');
		var loadMore = root.querySelector('.mcf-recipe-load-more');
		var filters = root.querySelectorAll('[data-mcf-filter]');
		var state = { page: 1, pages: 1, search: '', cuisine: '', dietary: '' };
		var debounceTimer;

		function setStatus(message) {
			status.textContent = message || '';
		}

		function endpoint(path, params) {
			var url = String(config.restUrl || '').replace(/\/$/, '') + path;
			var query = new URLSearchParams(params || {});
			return url + (query.toString() ? '?' + query.toString() : '');
		}

		function updateOptions(filtersData) {
			if (!filtersData) {
				return;
			}
			[
				{ name: 'cuisine', values: filtersData.cuisines || [], label: 'All cuisines' },
				{ name: 'dietary', values: filtersData.dietary || [], label: 'All dietary types' }
			].forEach(function (definition) {
				var select = root.querySelector('[data-mcf-filter="' + definition.name + '"]');
				if (!select || select.options.length > 1) {
					return;
				}
				definition.values.forEach(function (item) {
					var option = document.createElement('option');
					option.value = item.slug;
					option.textContent = item.name;
					select.appendChild(option);
				});
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
				'<p>' + esc(recipe.description || '') + '</p>' +
				labels(recipe.dietary, 'mcf-recipe-card__labels') +
				'<div class="mcf-recipe-card__meta">' + meta.join('') + '</div>' +
				'<button type="button" class="mcf-recipe-button" data-mcf-view="' + esc(recipe.id) + '">' + esc(config.i18n.viewRecipe) + ' <span aria-hidden="true">→</span></button>' +
				'</div></article>';
		}

		function load(reset) {
			if (reset) {
				state.page = 1;
				grid.innerHTML = '';
			}
			setStatus(config.i18n.loading);
			var params = {
				search: state.search,
				cuisine: state.cuisine,
				dietary: state.dietary,
				page: state.page,
				per_page: config.perPage || 8
			};
			fetch(endpoint('/recipes', params), { headers: { Accept: 'application/json' } })
				.then(function (response) {
					if (!response.ok) {
						throw new Error('search_failed');
					}
					return response.json();
				})
				.then(function (data) {
					updateOptions(data.filters);
					state.pages = Number(data.pages || 1);
					grid.insertAdjacentHTML('beforeend', (data.recipes || []).map(card).join(''));
					loadMore.hidden = state.page >= state.pages;
					setStatus(data.total ? data.total + ' ' + (data.total === 1 ? (config.i18n.resultsSingular || 'recipe found') : (config.i18n.resultsPlural || 'recipes found')) : config.i18n.noResults);
				})
				.catch(function () {
					setStatus(config.i18n.noResults);
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
			return '<div class="mcf-recipe-detail__toolbar">' +
				'<button type="button" class="mcf-recipe-link" data-mcf-close>← ' + esc(config.i18n.backToRecipes || 'Back to recipes') + '</button>' +
				'<span class="mcf-recipe-detail__badge">' + esc(recipe.ai_adapted ? (config.i18n.aiAdaptedBadge || 'AI-adapted') : (config.i18n.recipeBadge || 'Recipe')) + '</span>' +
				'</div>' +
				'<div class="mcf-recipe-detail__content">' +
				(image ? '<div class="mcf-recipe-detail__image">' + image + '</div>' : '') +
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
				'<button type="button" class="mcf-recipe-button mcf-recipe-button--green" data-mcf-adapt>' + esc(config.i18n.adaptRecipe) + '</button>' +
				'<button type="button" class="mcf-recipe-button mcf-recipe-button--outline" data-mcf-print>' + esc(config.i18n.printRecipe) + '</button>' +
				'</div></div></div>';
		}

		function openRecipe(id) {
			setStatus(config.i18n.loading);
			fetch(endpoint('/recipes/' + encodeURIComponent(id)), { headers: { Accept: 'application/json' } })
				.then(function (response) {
					if (!response.ok) {
						throw new Error('recipe_failed');
					}
					return response.json();
				})
				.then(function (recipe) {
					detail.dataset.recipeId = id;
					detail.innerHTML = detailHtml(recipe);
					detail.hidden = false;
					detail.focus();
					root.classList.add('mcf-recipe-library--detail-open');
					setStatus('');
				})
				.catch(function () {
					setStatus(config.i18n.noResults);
				});
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			state.search = search.value.trim();
			load(true);
		});
		search.addEventListener('input', function () {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				state.search = search.value.trim();
				load(true);
			}, 350);
		});
		Array.prototype.forEach.call(filters, function (filter) {
			filter.addEventListener('change', function () {
				state[filter.getAttribute('data-mcf-filter')] = filter.value;
				load(true);
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
			if (event.target.closest('[data-mcf-print]')) {
				root.classList.add('mcf-recipe-library--printing');
				window.print();
				window.setTimeout(function () {
					root.classList.remove('mcf-recipe-library--printing');
				}, 500);
				return;
			}
			if (event.target.closest('[data-mcf-adapt]')) {
				var instruction = window.prompt(config.i18n.adaptPrompt);
				if (!instruction) {
					return;
				}
				var current = detail.querySelector('[data-mcf-adapt]');
				current.disabled = true;
				current.textContent = config.i18n.adaptLoading;
				var recipeId = detail.querySelector('[data-mcf-close]') ? detail.dataset.recipeId : '';
				if (!recipeId) {
					recipeId = detail.dataset.recipeId || '';
				}
				fetch(endpoint('/recipes/' + encodeURIComponent(recipeId) + '/adapt'), {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
					body: JSON.stringify({ instruction: instruction })
				})
					.then(function (response) {
						if (!response.ok) {
							throw new Error('adapt_failed');
						}
						return response.json();
					})
					.then(function (recipe) {
						detail.innerHTML = detailHtml(recipe);
					})
					.catch(function () {
						current.disabled = false;
						current.textContent = config.i18n.adaptRecipe;
						window.alert(config.i18n.adaptError);
					});
			}
		});
		root.addEventListener('mcf:open', function (event) {
			if (event.detail && event.detail.id) {
				detail.dataset.recipeId = event.detail.id;
			}
		});
		load(true);
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('.mcf-recipe-library'), init);
	});
}());
