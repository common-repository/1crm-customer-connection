void function($) {
	"use strict";
	var tr = function(text) {
		if (window.ONECRM_LANGUAGE) {
			var translated = window.ONECRM_LANGUAGE[text];
			if (typeof translated !== 'undefined')
				return translated;
			return text;
		}
		return text;
	}
	
	var find = function(array, value, field) {
		if (!field) field = 'id';
		return array.find(function(elt) {return elt[field] == value});
	}

	var merge = function(obj, other) {
		for (var k in other) {
			if (typeof obj[k] === 'undefined') {
				obj[k] = other[k];
			} else if (typeof obj[k] !== 'object' || typeof other[k] !== 'object') {
				obj[k] = other[k];
			} else {
				merge(obj[k], other[k]);
			}
		}
	}

	var requiresQty = function(plan) {
		return plan && (plan.pricing_model == 'Tiered' || plan.pricing_model == 'Per Unit' || plan.pricing_model == 'Volume' || plan.pricing_model == 'Stairstep');
	}

	var getQueryParam = function(paramName) {
		var parts = window.location.search.split("?");
		if (parts.length != 2) return "";
		parts = parts[1].split("&");
		return parts.reduce(function(acc, val) {
			var nameval = val.split("=");
			var name = nameval[0];
			var value = "";
			if (nameval.length > 1) value = decodeURIComponent(nameval[1]);
			if (paramName == name) return value;
			return acc;
		}, "");
	}

	var S = window.OneCRMSubscriptions = function(container_id, init) {
		var self = this;

		$( document ).tooltip({
			items: "i.addon-tooltip, i.plan-tooltip",
			content: function() {
				var element = $( this );
				if ( element.is( ".addon-tooltip" ) ) {
					var addon = self.prod_meta.addons[element.data('id')];
					if (addon) return addon.popup_info;
				}
				if ( element.is( ".plan-tooltip" ) ) {
					var prodid = element.data('product-id');
					var planid = element.data('id');
					var plan = self.prod_meta.products[prodid].plans[planid];
					if (plan) return plan.popup_info;
				}
			}
		});

		this.optionsRenderer = OptionsRenderer();
		this.optionsValidator = OptionsValidator();
		this.prod_meta = init.prod_meta;
		this.data = {
			addons: {},
			options: {},
			customer: init.prod_meta.customer_data
		};

		if (init.edit) {
			this.editing = true;
			this.data = Object.assign({}, this.data, init.edit);
		}

		this.settings = init.settings;

		this.steps = [
			'product',
			'plan',
			'addons',
			'options',
			'invoice_notes',
			'customer',
			'payment',
			'confirm',
		];

		if (init.continue_subscription) {
			merge(this.data, init.continue_subscription);
		}
		this.return_url = init.return_url;
		if (init.compare_1crm_editions_url)
			this.compare_editions_url = init.compare_1crm_editions_url;

		this.data.signature = init.signature;
		if (!this.data.addons || Array.isArray(this.data.addons)) this.data.addons = {};
		if (!this.data.options || Array.isArray(this.data.options)) this.data.options = {};
		this.totals = {};
		this.errors = [];
		this.register_errors = [];

		this.container = $(container_id);
		this.step_container = $('<div>').appendTo(this.container);
		this.createContainers();
		this.errors_container = $('<div>').addClass('onecrm-subscription-errors').appendTo(this.container);
		this.buttons_container = $('<div>').addClass('onecrm-subscription-buttons').appendTo(this.container);

		if (!init.continue_subscription) {
			var product_search = getQueryParam("hosting").toLowerCase();
			var plan_search = getQueryParam("product").toLowerCase();
			var period_search = getQueryParam("period").toLowerCase();
			if (plan_search == 'startup_plus') plan_search = 'startup+';
			if (plan_search == 'infoathand') plan_search = 'professional';
			if (product_search == 'hosted') product_search = 'premise';
			var product_id = null;
			
			Object.keys(self.prod_meta['products']).forEach(function(k) {
				var product = self.prod_meta['products'][k];
				if (~product.name.toLowerCase().indexOf(product_search)) {
					product_id = k;
				}
			});
			if (product_id) {
				var plan_id = null;
				self.data.product = product_id;
				var prod = self.prod_meta['products'][product_id];
				Object.keys(prod['plans']).forEach(function(k) {
					var plan = prod['plans'][k];
					if (plan_search) {
						if (~plan.name.toLowerCase().indexOf(' '+ plan_search + ' ')) {
							if (period_search) {
								if (~plan.name.toLowerCase().indexOf(' '+ period_search + ' ')) {
									plan_id = k;
								}
							}
						}
					}
				});
				
			}
			if (plan_id) self.data.plan = plan_id;
		}

		if (init.continue_subscription) {
			this.calc_totals();
		};
		this.render_steps();
	};

	S.prototype.render_steps = function(steps) {
		if (!steps) steps = Object.keys(this.c);
		if (typeof steps === 'string') steps = [steps];
		if (!Array.isArray(steps)) return;
		this.cleanup();
		var self = this;
		steps.forEach(function(step) {
			self.c[step].text('');
			self['step_' + step]('render');
		});
		this.render_buttons();
		this.errors_container.text('');
		this.errors.forEach(function(e) {
			$('<div>').html(e).appendTo(self.errors_container);
		});
	};

	S.prototype.render_buttons = function() {
		var self = this;
		this.buttons_container.text('');
		var disabled = false;
		Object.keys(this.c).forEach(function(s) {
			if (!self['step_' + s]('can_continue')) {
				disabled = true;
			}
		});

		var next_label = tr(self.editing ? 'Update Subscription' : 'Create Subscription');
		$('<button>', {
			'class' : 'button onecrm-signup-button',
			disabled: disabled,
			on: {
				click: function() {
					self.finish_signup();
				}
			}
		}).text(next_label).appendTo(this.buttons_container);
	}


	S.prototype.step_product = function(action) {
		var self = this;

		if (action == 'valid') {
			var keys = Object.keys(self.prod_meta.products);
			if (keys.length == 1) {
				self.data.product = keys[0];
			}
			return keys.length > 1;
		}
		
		if (action == 'can_continue') {
			return !!this.data.product;
		}

		if (action != 'render') return;

		var container = self.c.product;
		container.text('');

		$('<h3>').text(tr('onecrm_product_label')).appendTo(container);

		var select = $('<select>',{
			on: {
				change: function() {
					self.data.product = this.value;
					self.render_steps();
				}
			}
		}).appendTo(container);
		Object.keys(self.prod_meta.products).forEach(function(k) {
			var prod = self.prod_meta.products[k];
			if (!self.data.product) {
				self.data.product = prod.id;
			}
			var active = prod.id == self.data.product ? ' active' : '';
			var div = $('<option>', {
				'value' : prod.id,
			}).text(prod.name);
			if (prod.id == self.data.product) {
				div.attr('selected', true);
			}
			select.append(div);
		});
	};

	S.prototype.step_confirm = function(action) {
		if (action == 'valid') {
			return true;
		}
		if (action == 'can_continue') {
			return true;
		}
		if (action != 'render') return;
	}

	S.prototype.step_plan = function(action) {
		var self = this;
		if (action == 'valid') {
			return true;
		}
		if (action == 'can_continue') {
			if (!self.data.plan) return false;
			var prod = self.prod_meta['products'][self.data.product];
			var cur_plan = prod.plans[self.data.plan];
			if (requiresQty(cur_plan)) {
				if (!(this.data.qty > 0)) return false;
				cur_plan.qty_min = parseInt(cur_plan.qty_min) || 0;
				cur_plan.qty_max = parseInt(cur_plan.qty_max) || 0;
				if (cur_plan.qty_min && this.data.qty < cur_plan.qty_min) {
					return false;
				}
				if (cur_plan.qty_max && this.data.qty > cur_plan.qty_max) {
					return false;
				}
			}
			return true;
		}


		if (action != 'render') return;

		var self = this;
		var container = self.c.plan;
		container.text('');

		var prod = self.prod_meta['products'][self.data.product];
		if (!~Object.keys(prod.plans).indexOf(self.data.plan)) {
			self.data.plan = null;
		}
		$('<h3>').text(tr('onecrm_plan_label')).appendTo(container);
		var select = $('<select>', {
			on: {
				change: function() {
					self.data.plan = this.value;
					self.render_steps();
				}
			}
		}).addClass('onecrm-plan-select').appendTo(container);

		var compare_link_container = $('<span>');
		$('<a>', {href: self.compare_editions_url, target: '_blank'})
			.addClass('onecrm-compare-link').text(tr('Compare Editions'))
			.appendTo(compare_link_container);
		compare_link_container.appendTo(container);

		var keys = Object.keys(prod.plans).sort(function(a, b) {
			var pa = prod.plans[a];
			var pb = prod.plans[b];
			var oa = pa.display_order || 0;
			var ob = pb.display_order || 0;
			return oa - ob;
		});
		keys.forEach(function(k) {
			var plan = prod.plans[k];
			if (!self.data.plan) {
				self.data.plan = plan.id;
			}
			var cycle = tr('period_' + plan.billing_cycle);
			if (plan.billing_cycle_mult > 1) {
				cycle += " x" + plan.billing_cycle_mult;
			}
			var active = plan.id == self.data.plan ? ' active' : '';
			var option = $('<option>', {
				value: plan.id
			}).text(plan.long_name || plan.name);
			if (plan.id == self.data.plan) {
				option.attr('selected', true);
			}
			select.append(option);
		});
		var cur_plan = prod.plans[self.data.plan];
		var qty_div = self.render_qty_selector(container, cur_plan, false, self.data.qty, function() {
			this.value = self.check_qty_value(cur_plan, this.value);
			var value = this.value;
			var qty = parseInt(value);
			if (value !== '' && (isNaN(qty) || qty < 1)) {
				this.value = self.data.qty;
			} else if (qty != self.data.qty) {
				self.data.qty = qty;
				self.copy_qty_to_addons();
			}
			self.calc_totals();
			self.render_buttons();
		});
		$('<span>').addClass('onecrm-plan-total').appendTo(qty_div);
		self.calc_totals();
	};
	
	S.prototype.copy_qty_to_addons = function() {
		var self = this;
		var plan = self.prod_meta.products[self.data.product].plans[self.data.plan];
		if (!plan) return;
		if (!plan.addons) return;
		plan.addons.forEach(function(a) {
			var addon = self.addonForPlan(plan, self.prod_meta.addons[a]);
			if (addon.copy_plan_qty !== '1') return;
			if (self.data.addons[a]) {
				self.data.addons[a].qty = self.data.qty;
			}
		});
	};

	S.prototype.step_addons = function(action) {
		var self = this;
		if (action == 'valid') {
			if (!this.data.plan || !this.data.product) return false;
			var plan = this.prod_meta.products[this.data.product].plans[this.data.plan];
			if (!plan.addons) return false;
			return plan.addons.length > 0;
		}

		if (action == 'can_continue') {
			var ok = true;
			Object.keys(this.data.addons).forEach(function(a) {
				var addon = self.prod_meta.addons[a];
				var qty = self.data.addons[a].qty;
				if (requiresQty(addon) && (qty < 1 || !qty)) // undefined < 1 === false
					ok = false;
			});
			return ok;
		}

		if (action != 'render') return;

		var container = self.c.addons;
		container.text('');
		var plan = self.prod_meta.products[self.data.product].plans[self.data.plan];
		if (!plan) return;
		if (!plan.addons) return;
		$('<h3>').text(tr('onecrm_addons_label')).appendTo(container);
		$('<div>').text(tr('onecrm_addons_description')).appendTo(container);

		var sortedAddons = plan.addons.slice().sort(function(a, b) {
			var aa = self.prod_meta.addons[a];
			var ab = self.prod_meta.addons[b];
			var oa = aa.display_order || 0;
			var ob = ab.display_order || 0;
			return oa - ob;
		});


		sortedAddons.forEach(function(a) {
			var addon = self.addonForPlan(plan, self.prod_meta.addons[a]);
			$('<div>')
				.addClass('onecrm-subscription-product-name')
				.append(
					$('<label>')
					.append(
						$('<input>', {
							type: 'checkbox',
							checked: !!self.data.addons[a],
							on: {
								click: function() {
									if (this.checked) {
										var default_qty;
										if (addon.copy_plan_qty === '1') {
											default_qty = self.data.qty || null;
										} else {
											default_qty = addon.default_qty || null;
										}
										self.data.addons[a] = {_dummy: false, qty: default_qty};
										self.check_mutex_addons(plan.addons, a, addon.addons_group);
									}
									else delete self.data.addons[a];
									self.render_steps();
								}
							}
						})
					)
					.append($('<i>').html('&nbsp;&nbsp;'))
					.append(
						addon.long_name || addon.name
					)
					.append(addon.popup_info ?  $('<span class="onecrmhelp"><i class="addon-tooltip fas fa-info-circle" data-id="' + addon.id + '"></i></span>') : '')
				)
				.append(self.render_plan_price(addon, plan))
				.appendTo(container);
			if (self.data.addons[a]) {
				var qty_div = self.render_qty_selector(container, addon, true, self.data.addons[a].qty, function() {
					var value = this.value;
					var qty = parseInt(value);
					if (value !== '' && (isNaN(qty) || qty < 1)) {
						this.value = self.data.addons[a].qty;
					} else if (qty != self.data.addons[a].qty) {
						self.data.addons[a].qty = qty;
					}
					self.calc_totals();
					self.render_buttons();
				});
				$('<span>').addClass('onecrm-addon-total addon-' + a).appendTo(qty_div);
			}
		});
		this.calc_totals();
	};

	S.prototype.check_mutex_addons = function(addons, active_addon, group) {
		var self = this;
		addons.forEach(function(a) {
			var addon = self.prod_meta.addons[a];
			if (!addon.addons_group || addon.addons_group != group || a == active_addon)
				return;
			delete self.data.addons[a];
		});
	};

	S.prototype.validate_options = function() {
		$('.onecrm-subscription-option-error').text('');
		var ok = true;
		var self = this;
		var errors = {};
		if (!this.data.plan || !this.data.product) return ok;
		var plan = this.prod_meta.products[this.data.product].plans[this.data.plan];
		if (plan && plan.options) {
			plan.options.forEach(function(o) {
				var opt = self.prod_meta.options[o];
				var value = self.data.options[opt.input];
				var error = self.optionsValidator(opt, value);
				if (error) {
					ok = false;
					errors[opt.input] = error;
				}
			});
		}
		Object.keys(errors).forEach(function(k) {
			var err = errors[k];
			$('#onecrm-subscription-option-error-'+k).text(err);
		});
		return ok;
	};

	S.prototype.step_invoice_notes = function(action) {
		var self = this;
		if (action == 'valid') {
			return true;
		}

		if (action == 'can_continue') {
			return true;
		}
		if (action != 'render') return;
		if (!self.prod_meta.edit_invoice_notes || !self.data.customer.user_id) return;
		var container = self.c.invoice_notes;
		$('<h3>').text(tr('Invoice Notes')).appendTo(container);

		var update = function() {
			self.data.invoice_notes = this.value;
		};
		var input = $('<textarea>', {
			style: "width: 100%",
			on: {
				keyup: update,
				change: update
			}
		}).append(self.data.invoice_notes);
		input.appendTo(container);
	}

	S.prototype.step_options = function(action) {
		var self = this;
		if (action == 'valid') {
			if (!this.data.plan || !this.data.product) return false;
			var plan = this.prod_meta.products[this.data.product].plans[this.data.plan];
			if (!plan.options) return false;
			return plan.options.length > 0;
		}

		if (action == 'can_continue') {
			if (!this.data.plan || !this.data.product) return false;
			return self.validate_options();
		}

		if (action != 'render') return;
		var plan = this.prod_meta.products[this.data.product].plans[this.data.plan];
		if (!plan) return;
		if (!plan.options) return;
		var container = self.c.options;
		$('<h3>').text(tr('Options')).appendTo(container);
		plan.options.forEach(function(o) {
			var opt = self.prod_meta.options[o];
			var updateOption = function(value) {
				self.data.options[opt.input] = value;
				self.validate_options();
				self.render_buttons();
			}
			if (opt.input === 'licensed_user_onecrm' && !self.data.options[opt.input]) {
				self.data.options[opt.input] = self.data.customer.account_name;
			}
			var rendered = self.optionsRenderer(opt, self.data.options[opt.input], (self.data.subscription_id && opt.updateable !== '1') ? null : updateOption);
			var desc = opt.description || '';
			if (desc) 
				rendered.append($('<div>').text(desc));
			rendered.append($('<div>', {'class' : 'onecrm-subscription-option-error', id : 'onecrm-subscription-option-error-' + opt.input}));
			container.append(rendered);
		});
		self.validate_options();
	};

	S.prototype.step_payment = function(action, perform) {
		var self = this;
		if (action == 'can_continue') {
			return !!this.data.customer.payment_method;
		}
		if (action == 'process') {
		}
		if (action != 'render') return;
		
		var container = self.c.payment;
		container.text('');

		if (!self.data.customer.customer_id) return;

		$('<h3>').text(tr('Payment method')).appendTo(container);
		var methods = self.prod_meta.customer_data.payment_methods || [];
		methods.forEach(function(m) {
			$('<div class="onecrm-subscription-option">')
				.append(
					$('<span class="onecrm-subscription-option-label">')
					.append($('<input>', {
						type: "radio", name: "payment_method", value: m.id, id: "payment-method-" + m.id,
						checked: self.data.customer.payment_method == m.id,
						on : {click: function() { self.data.customer.payment_method = m.id; self.render_buttons(); }}
					}))
					.append(
						$('<label for="payment-method-' + m.id + '">')
						.append('&nbsp;').append(m.name)
					)
				)
				.appendTo(container);

		});
		if (!methods.length) {
			$('<p>').text(tr('No cards on file')).appendTo(container);
		}
		
		$('<button>', {
			'class' : 'button onecrm-signup-button',
			on: {
				click: function() {
					$('.onecrm-signup-button').attr('disabled', true);
					var postData = Object.assign({}, self.data.signature,
						{
							data: self.data,
							onecrm_post_action: 'signup_add_card'
						}
					);
					$.post("", postData)
						.done(function(data, textStatus, jqXHR ) {
							var inner;
							self.iframe_div = $('<div>', {
								'class': 'onecrm-subscription-dimmer',
								on : {
									click: function() {
										self.iframe_div.remove();
										self.iframe_div = null;
									}
								}
							});
							var iframe = $('<iframe>', {'class' : 'onecrm-subscription-dimmer-iframe'})
								.appendTo(inner = $('<div>', {'class': 'onecrm-subscription-dimmer-inner'}).appendTo(self.iframe_div));
							iframe.attr('src', data);
							$(document.body).append(self.iframe_div);
							window.ONECRM_REMOVE_DIMMER = function(url) {
								if (self.iframe_div) {
									self.iframe_div.remove();
									self.iframe_div = null;
									window.location.href = url;
								}
							};
							if (window.innerHeight < 640) {
								iframe.css('height',  '' + (window.innerHeight - 40) + 'px');
							}
						})
						.fail(function() {
						})
						.always(function() {
							$('.onecrm-signup-button').attr('disabled', false);
							self.render_buttons();
						});
				}
			}
		}).text(tr('Manage Payment Methods')).appendTo(container);

	}

	S.prototype.step_customer = function(action, perform) {
		var self = this;

		if (action == 'valid') {
			return true;
		}

		if (action == 'can_continue') {
			var ok = true;
			var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
			if (!re.test(self.data.customer.email)) ok = false;
			return ok;
		}

		if (action == 'process') {
			if (self.data.customer.user_id) return false;

			$('.onecrm-signup-button').attr('disabled', true);
			var postData = Object.assign(
				{},
				self.data.signature,
				{
					onecrm_post_action: self.data.customer.old_customer ? 'signup_login' : 'signup_register',
					email: self.data.customer.email,
					password: self.data.customer.password,
					products: Object.keys(self.prod_meta.products),
					first_name: self.data.customer.first_name,
					last_name: self.data.customer.last_name,
					account_name: self.data.customer.account_name,
					user_name: self.data.customer.user_name,
					data: self.data
				}
			);
			self.register_errors = [];
			$.post("", postData)
				.done(function(data, textStatus, jqXHR ) {
					if (typeof data === 'object') {
						if (data.errors) {
							Object.keys(data.errors).forEach(function(e) {
								self.register_errors.push(data.errors[e][0]);
							});
						} else {
							if (!self.data.customer.old_customer) {
								self.registered_new_user = true;
							}
							if (data.prod_meta) {
								if (data.prod_meta.addons) {
									self.prod_meta.addons = data.prod_meta.addons;
								}
								if (data.prod_meta.products) {
									self.prod_meta.products = data.prod_meta.products;
								}
								if (data.prod_meta.options) {
									self.prod_meta.options = data.prod_meta.options;
								}
								if (data.prod_meta.customer_data) {
									self.data.customer = data.prod_meta.customer_data;
									self.prod_meta.customer_data = data.prod_meta.customer_data;
								}
								self.prod_meta.edit_invoice_notes = data.prod_meta.edit_invoice_notes;
							}
						}
					}
					self.render_steps();
				})
				.fail(function() {
				})
				.always(function() {
					self.render_buttons();
				});
			return true;
		}

		if (action != 'render') return;

		var container = self.c.customer;
		container.text('');

		var update = function(field) {
			return function() {
				self.data.customer[field] = this.value;
				self.render_buttons();
			}
		};


		$('<h3>').text(tr('Customer details')).appendTo(container);

		var renderField = function(label, field, type, no_edit) {
			var input;
			if (!no_edit) {
				input = $('<input>', {
					type: type || 'text',
					value: self.data.customer[field],
					on: {
						keyup: update(field),
						change: update(field)
					}
				});
			} else {
				input = self.data.customer[field] || '';
			}
			$('<div class="onecrm-subscription-option">')
				.append($('<span class="onecrm-subscription-option-label">').text(label))
				.append(input)
				.appendTo(container);
			;
		};

		var readOnly = !!self.data.customer.user_id;

		if (!self.data.customer.user_id && !self.registered_new_user) {
			var updateFields = function() {
				self.data.customer.old_customer = this.value;
				self.render_steps();
			}
			$('<div class="onecrm-subscription-option">')
				.append(
					$('<span class="onecrm-subscription-option-label">')
					.append($('<input>', {
						type: "radio", name: "old_customer", value: "", id: "onecrm-new-customer",
						checked: !self.data.customer.old_customer,
						on : {click: updateFields}
					}))
					.append(
						$('<label for="onecrm-new-customer">')
						.append('&nbsp;').append(tr(' I am a new customer'))
					)
				)
				.append(
					$('<span class="onecrm-subscription-option-label">')
					.append($('<input>', {
						type: "radio", name: "old_customer", value: "1", id: "onecrm-old-customer",
						checked: !!self.data.customer.old_customer,
						on : {click: updateFields}
					}))
					.append(
						$('<label for="onecrm-old-customer">')
						.append('&nbsp;').append(tr(' I am a returning customer'))
					)
				)
			.appendTo(container);
		}

		if (!self.registered_new_user) {
			renderField(tr('Email address'), 'email', 'email', readOnly);
			if (!self.data.customer.old_customer) {
				if (!self.data.customer.user_id) {
					renderField(tr('User name'), 'user_name');
				}
				renderField(tr('First name'), 'first_name', null, readOnly);
				renderField(tr('Last name'), 'last_name', null, readOnly);
				renderField(tr('Company name'), 'account_name', null, readOnly);
			} else {
				renderField(tr('Password'), 'password', 'password');
			}
		}

		var errors_container = $('<div>', {
				'class' : 'onecrm-subscription-errors',
		}).appendTo(container);

		if (self.registered_new_user) {
			$('<div>').text(tr('confirm_email')).appendTo(container);
		}

		if (!self.data.customer.user_id && !self.registered_new_user) {
			$('<button>', {
				'class' : 'button onecrm-signup-button',
				on: {
					click: function() {
						self.step_customer('process');
					}
				}
			}).text(tr(self.data.customer.old_customer ? 'Login' : 'Register me & Continue')).appendTo(container);

		}
		errors_container.text('');
		this.register_errors.forEach(function(e) {
			$('<div>').html(e).appendTo(errors_container);
		});
	}

	S.prototype.render_totals = function() {
		var self = this;
		var container = self.step_container;
		var detailsContainer;
		if (this.totals.discount > 0) {
			$('<div>')
				.append(
					$('<h4>', {
					'class': '',

					}).text(tr('Subtotal: ') + this.formatPrice(self.totals.subtotal))
				)
				.appendTo(container);
			$('<div>')
				.append(
					$('<h4>', {
					'class': '',

					}).text(tr('Discount: ') + this.formatPrice(self.totals.discount))
				)
				.appendTo(container);
		}
		$('<div>')
			.append(
				$('<h4>', {
				'class': '',

				}).text(tr('Order Total: ') + this.formatPrice(self.totals.total))
			)
			.appendTo(container);
	};

	S.prototype.render_customer_details = function() {
		var self = this;
		var container = self.step_container;
		var detailsContainer;
		$('<div>')
			.append(
				$('<h4>', {
				'class': '',

				}).text(tr('Customer details'))
			)
			.append(detailsContainer = $('<div>'))
			.appendTo(container);
		var renderField = function(label, field) {
			$('<div class="onecrm-subscription-option">')
			.append($('<span class="onecrm-subscription-option-label">').text(label))
			.append($('<span>').text(self.data.customer[field]))
			.appendTo(detailsContainer);
		};
		renderField(tr('Email address'), 'email');
		renderField(tr('First name'), 'first_name');
		renderField(tr('Last name'), 'last_name');
		renderField(tr('Company name'), 'account_name');
	};

	S.prototype.render_qty_selector = function(container, plan, is_addon, qty, update) {
		var self = this;
		var ret;
		if (!requiresQty(plan)) return $('<div>');
		if (plan.copy_plan_qty === '1') return $('<div>').html('&nbsp;').appendTo(container);
		var input;
		if (plan.pricing_model == 'Stairstep')
			input = self.build_stairstep_select(plan, qty, update);
		else
			input = self.build_qty_input(plan, qty, update);
		var div = $('<div>', {'class' : 'onecrm-subscription-qty-input'})
			.append(
				ret = $('<div>')
				.append(plan.qty_label)
				.append('&nbsp;&nbsp;')
				.append(input)
			)
			.appendTo(container);
		$('<div>').text(plan.qty_comment || '')
			.append(
				!is_addon && plan.popup_info ?  
					$('<span class="onecrmhelp">&nbsp;&nbsp;<i class="plan-tooltip fas fa-info-circle" data-id="' + plan.id + '" data-product-id="' + plan.product_id + '"></i></span>') 
					: ''
			)
			.appendTo(container);
		ret.addClass('onecrm-subscription-required');
		return ret;
	};

	S.prototype.build_stairstep_select = function(plan, value, update) {
		var select = $('<select>', {style: 'width: initial', on: {change: update}});
		if (!value) {
			var opt = $('<option>', {value: ''});
			select.append(opt);
		}
		var tiers = plan.pricing_tiers;
		var last_qty = 0;
		var last_price = 0;
		var last_label = '';
		if (tiers.length > 0) {
			last_price = tiers[0].amount;
			last_label = tiers[0].description;
		}
		var selected;
		for (var i = 1; i < tiers.length; i++) {
			var t = tiers[i];
			last_qty = t.qty - 1;
			var label = last_label || (t.qty - 1);
			var opt = $('<option>', {value: last_qty}).text(label);
			select.append(opt);
			if (value >= last_qty) {
				selected = opt;
			}
			last_label = t.description;
		}
		opt = $('<option>', {value: last_qty + 1}).text(last_label || (' > ' + last_qty));
		select.append(opt);
		if (value >= last_qty + 1) {
			selected = opt;
		}
		if (selected)
			selected.attr('selected', true);
		return select;
	};

	S.prototype.build_qty_input = function(plan, qty, update) {
		var value = this.check_qty_value(plan, qty);

		var params = {
			type: 'number',
			value: value,
			on: {
				keyup: update,
				change: update,
			}
		};

		var plan_qty_min = parseInt(plan.qty_min) || 0;
		var plan_qty_max = parseInt(plan.qty_max) || 0;

		if (plan_qty_min > 0)
			params.min = plan_qty_min

		if (plan_qty_max > 0)
			params.max = plan_qty_max

		return $('<input>', params);
	};

	S.prototype.check_qty_value = function(plan, qty) {
		var plan_qty_min = parseInt(plan.qty_min) || 0;
		var plan_qty_max = parseInt(plan.qty_max) || 0;
		var value = qty;
		var intValue = parseInt(qty);

		if (value !== '' && !isNaN(intValue)) {
			if (plan_qty_max > 0 && value > plan_qty_max)
				value = plan_qty_max

			if (value < plan_qty_min)
				value = plan_qty_min
		}

		return value;
	};

	S.prototype.render_plan_price = function(plan_or_addon, plan) {
		switch (plan_or_addon.pricing_model) {
			case 'Flat Fee':
			case 'Per Unit':
				var amount = plan_or_addon.amount;
				if (plan) amount *= this.addon_price_mult(plan, plan_or_addon);
				return $('<span>').text(this.formatPrice(amount));
			case 'Tiered':
			case 'Volume':
				var min = null;
				plan_or_addon.pricing_tiers.forEach(function(t) {
					if (min === null || t.amount < min) {
						min = t.amount;
					}
				});
				if (plan) min *= this.addon_price_mult(plan, plan_or_addon);
				return $('<span>').text(tr('From') + ' ' + this.formatPrice(min));
				break;
		}
	};

	S.prototype.addon_price_mult = function (plan,addon) {
		var mult = 1;
		var plan_periods = plan.billing_cycle_mult;
		var addon_periods = addon.billing_cycle_mult;
		switch (plan.billing_cycle + '|' + addon.billing_cycle) {
			case 'Monthly|Monthly':
			case 'Yearly|Yearly':
			case 'Weekly|Weekly':
				mult = plan_periods / addon_periods;
				break;
			case 'Yearly|Monthly':
				mult = plan_periods * 12 / addon_periods;
				break;
		};
		return mult;
	};
	
	S.prototype.calc_totals = function() {
		var self = this;
		this.totals = {
			addons: {},
			discount: 0,
			subtotal: 0,
			total: 0
		};
		var plan;
		var discounts = [];
		if (this.data.customer && this.data.customer.customer_level && this.data.customer.customer_level.discounts) {
			var disc = this.data.customer.customer_level.discounts;
			if (disc.default) {
				discounts.push(disc.default);
			}
			if (disc.per_plan[this.data.plan]) {
				discounts.push(disc.per_plan[this.data.plan]);
			}
		}
		if (this.data.plan) {
			plan = this.prod_meta.products[this.data.product].plans[this.data.plan];
			var amount = this.plan_total(plan, this.data.qty);
			this.totals.plan = amount;
			$('.onecrm-plan-total').text( amount > 0 ? ' = ' + this.formatPrice(this.totals.plan) : '');
			this.totals.subtotal += amount;
			this.totals.discount += this.calc_plan_discount(discounts, amount, false);
		}
		Object.keys(this.data.addons).forEach(function(a) {
			var addon = self.prod_meta.addons[a];
			var amount = self.plan_total(addon, self.data.addons[a].qty, plan);
			self.totals.addons[a] = amount;
			$('.onecrm-addon-total.addon-'+a).text( amount > 0 ? ' = ' + self.formatPrice(amount) : '');
			self.totals.subtotal += amount;
			self.totals.discount += self.calc_addon_discount(discounts, amount, a, false);
		});
		this.totals.discount += this.calc_plan_discount(discounts, this.totals.subtotal, true);
		Object.keys(this.data.addons).forEach(function(a) {
			var addon = self.prod_meta.addons[a];
			self.totals.discount += self.calc_addon_discount(discounts, self.totals.subtotal, a, true);
		});
		this.totals.total = this.totals.subtotal - this.totals.discount;
		if (this.totals.total < 0)
			this.totals.total = 0;
	};

	S.prototype.calc_plan_discount = function(discounts, amount, is_total) {
		var self = this;
		var discount = 0;
		var applicapble = discounts.filter(function(d) {
			if ( (d.discount_mode == 'Specific Item') ^ !is_total) return false;
			if (d.plan_mode == 'All Plans') return true;
			return !!d.plans.find(function(p) {
				return p.id == self.data.plan;
			});
		});
		applicapble.forEach(function(d) {
			if (d.discount_type == 'percentage') {
				discount += amount * d.discount_percentage / 100;
			} else {
				discount += d.discount_amount;
			}
		});
		return discount;
	};

	S.prototype.calc_addon_discount = function(discounts, amount, addon_id, is_total) {
		var self = this;
		var discount = 0;
		var applicapble = discounts.filter(function(d) {
			if ( (d.discount_mode == 'Specific Item') ^ !is_total) return false;
			if (d.addon_mode == 'All Addons') return true;
			return !!d.addons.find(function(a) {
				return a.id == addon_id;
			});
		});
		applicapble.forEach(function(d) {
			if (d.discount_type == 'percentage') {
				discount += amount *  d.discount_percentage / 100;
			} else {
				discount += d.discount_amount;
			}
		});
		return discount;

	};

	S.prototype.formatPrice = function(price) {
		price = parseFloat(price).toFixed(2);
		return this.settings.symbol_after ? price + this.settings.symbol : this.settings.symbol + price;
	};

	S.prototype.plan_total = function(plan_or_addon, qty, plan) {
		var self = this;
		var amount;
		switch (plan_or_addon.pricing_model) {
			case 'Per Unit':
				amount =(qty || 0) * plan_or_addon.amount;
				break;
			case 'Volume':
				var total = 0;
				var max = 0;
				plan_or_addon.pricing_tiers.forEach(function(t) {
					if (qty >= t.qty && max < t.qty) {
						total = t.amount;
						max = t.qty;
					}
				});
				amount = qty * total;
				break;
			case 'Tiered':
				var total = 0;
				plan_or_addon.pricing_tiers.slice().reverse().forEach(function(t) {
					if (qty >= t.qty) {
						var used = qty - t.qty + 1;
						if (used > 0) {
							total += used * t.amount;
							qty -= used;
						}
					}
				});
				amount = total;
				break;
			case 'Stairstep':
				plan_or_addon.pricing_tiers.forEach(function(t) {
					if (qty >= t.qty) {
						amount = t.amount;
					}
				});
				break;
			default: amount = parseFloat(plan_or_addon.amount);
		}
		if (plan) {
			amount *= self.addon_price_mult(plan, plan_or_addon);
		}
		return amount;
	};
	
	S.prototype.finish_signup = function() {
		var self = this;
		$('.onecrm-signup-button').attr('disabled', true);

		var postData = Object.assign({}, this.data.signature,
			{
				subscription: {
					addons: this.data.addons,
					options: this.data.options,
					plan: this.data.plan,
					product: this.data.product,
					qty: this.data.qty,
					invoice_notes: this.data.invoice_notes
				},
				customer: this.data.customer,
				onecrm_post_action: 'signup_create',
				subscription_id: this.data.subscription_id
			}
		);
		$.post("", postData)
			.done(function(data, textStatus, jqXHR ) {
				self.render_steps();
				if (data.error) {
					self.errors_container.text(data.error);
					return;
				}
				if (self.return_url) {
					window.location.href = self.return_url;
				}
			})
			.fail(function() {
			})
			.always(function() {
				self.render_buttons();
			});
	};

	S.prototype.cleanup = function() {
		if (!this.data.plan) return;
		var self = this;
		var plan = this.prod_meta.products[this.data.product].plans[this.data.plan];
		if (!plan) {
			var plans = this.prod_meta.products[this.data.product].plans;
			var plan_id = Object.keys(plans)[0];
			plan = plans[plan_id];
		}
		if (!plan) {
			self.data.plan = null;
			return;
		}
		if (!plan.addons) {
			this.data.addons = {}
		} else {
			var remove = Object.keys(this.data.addons||{}).filter(function(a) {
				return !~plan.addons.indexOf(a);
			});
			remove.forEach(function(a) {
				delete self.data.addons[a];
			});
		}
		if (!plan.options) {
			this.data.options = {}
		} else {
			var remove = Object.keys(this.data.options||{}).filter(function(input) {
				var opt = Object.entries(self.prod_meta.options).find(function(opt) {
					return opt[1].input == input;
				});
				return !~plan.options.indexOf(opt[1].id);
			});
			remove.forEach(function(o) {
				delete self.data.options[o];
			});
		}
	};
	
	S.prototype.addonForPlan = function(plan, addon) {
		if (!plan.addons) return null;
		if (!~plan.addons.indexOf(addon.id)) return null;
		return addon;
	};
	
	S.prototype.createContainers = function() {
		var self = this;
		var left = $('<div>', {	'class': 'onecrm-step-column'}).appendTo(self.container);
		var right = $('<div>', {	'class': 'onecrm-step-column'}).appendTo(self.container);

		self.c = {};
		var lc = ['product', 'plan', 'addons', 'options', 'invoice_notes'];
		var rc = ['customer', 'payment'];
		lc.forEach(function(cname) {
			self.c[cname] = $('<div>', {
				'class': 'onecrm-step-container'
			}).appendTo(left);
		});
		rc.forEach(function(cname) {
			self.c[cname] = $('<div>', {
				'class': 'onecrm-step-container'
			}).appendTo(right);
		});
	};

	var OptionsValidator = function() {
		var validators = {
			varchar: function(opt, value) {
				if (opt.validation_rule) {
					try {
						var re = new RegExp(opt.validation_rule);
						if (!re.test(value)) {
							return opt.validation_message || tr('Invalid value');
						}
					} catch(e) {
					}
				}
			}
		};

		var validateRequired = function(opt, value) {
			var required = opt.required;
			if (typeof required == 'string') required = parseInt(required);
			if (required && !value) {
				return opt.required_message || tr('This option is required');
			};
		};

		return function(opt, value) {
			var error = validateRequired(opt, value);
			if (!error) {
				var validator = validators[opt.type];
				if (validator) {
					error = validator(opt, value);
				}
			}
			if (typeof error != 'string') error = null;
			return error;
		};
	};

	var OptionsRenderer = function() {
		
		var viewRenderers = {
			varchar: function(opt, value, update, label) {
				return $('<div class="onecrm-subscription-option">')
					.append(label)
					.append(value);
			},
			bool: function(opt, value, update, label) {
				return $('<div class="onecrm-subscription-option">')
					.append(label)
					.append(tr(value ? 'Yes' : 'No'));
			},

			enum: function(opt, value, update, label) {
				var options = opt.options.split("\n").filter(function(o) {return o.length});
				options.unshift('');
				var display = '';
				options.forEach(function(o) {
					var v, l;
					var parts = o.split('|');
					if (parts.length > 1) {
						v = parts[0];
						l = parts[1];
					} else {
						v = o;
						l = o;
					}
					if (v == value) display = l;
				});
				return $('<div class="onecrm-subscription-option">')
					.append(label)
					.append(display);
			}
		}

		var editRenderers = {
			varchar: function(opt, value, update, label) {
				var suffix = opt.suffix_label || '';
				suffix = suffix ? ' ' + suffix : '';
				var brk = suffix ? $('<br>') : '';
				return $('<div class="onecrm-subscription-option">')
					.append(label)
					.append(brk)
					.append($('<input>', {
						type: 'text',
						value: value,
						on: {
							keyup: function() {update(this.value.trim())},
							change: function() {update(this.value.trim())},
						}
					}))
					.append(suffix)
				;
			},
			
			bool: function(opt, value, update, label) {
				return $('<div class="onecrm-subscription-option">')
				.append(
					$('<label>')
					.append(
						$('<input>', {
							type: 'checkbox',
							checked: !!value,
							on: {
								click: function() {update(this.checked)},
							}
						})
					)
					.append($('<i>').html('&nbsp;&nbsp;'))
					.append(label)
				)
				;
			},

			enum: function(opt, value, update, label) {
				var options = opt.options.split("\n").filter(function(o) {return o.length});
				options.unshift('');
				var options = options.map(function(o) {
					var v, l;
					var parts = o.split('|');
					if (parts.length > 1) {
						v = parts[0];
						l = parts[1];
					} else {
						v = o;
						l = o;
					}
					return $('<option>', {value: v, selected: v == value}).text(l);
				});
				return $('<div class="onecrm-subscription-option">')
					.append(label)
					.append($('<select>', {
						type: 'text',
						value: value,
						on: {
							change: function() {update(this.value)}
						}
					}).append(options) )
				;
			}
		};

		return function(opt, value, update) {
			var renderers = update ? editRenderers : viewRenderers;
			var render = renderers[opt.type];
			if (render) {
				var label = $('<span class="onecrm-subscription-option-label">').text(opt.label);
				var required = opt.required;
				if (typeof required == 'string') required = parseInt(required);
				if (required) label.addClass('onecrm-subscription-required');
				return render(opt, value, update, label);
			}
		};
	};

}(jQuery);
