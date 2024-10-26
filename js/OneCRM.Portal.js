// Project global init:
jQuery(function ($) {
	"use strict";
    // bootstrap:
    OneCRM.Portal.App.init();

    // feature-specific event handlers:
    $('#expand-noteform').on('click', function () {
        $("#add-note-container").toggleClass('expanded');
    });

});

// Project namespace OneCRM.Portal:
(function ($) {
	"use strict";
    window.OneCRM || (window.OneCRM = {});
    OneCRM.Portal || (OneCRM.Portal = {});
})(jQuery);

// Project namespace OneCRM.Portal.Cookie:
(function ($) {
	"use strict";
    var portal = window.OneCRM.Portal;
    portal.Cookie || (portal.Cookie = {});
    var cookie = portal.Cookie;
    cookie.set = function (cname, cvalue, exdays) {
        var d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    };
    cookie.get = function (cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) === 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    };
})(jQuery);

// Project namespace OneCRM.Portal.App:
(function ($) {
	"use strict";
    var portal = window.OneCRM.Portal;
    portal.App || (portal.App = {});
    var app = portal.App;
    app.templates || (app.templates = {all_options: "{all_controls}"});
    app.tScripts || (app.tScripts = {});
    app.replace || (app.replace = {});
    app.replaceProtocolRelative = true;
    app.plans={};
    app.addons={};

    var R = app.replace, T = app.templates; // closure shorthand

    $.extend(app.replace,{
        translations: {
            'lang.Infinity':    'Unlimited',
            'pricing.Tiered':   'Each {qty_label}',
            'pricing.Stairstep':'Package Quantity',
            'pricing.Volume':   'Volume Discount',
            'pricing.Per Unit': 'Each {qty_label}',
            'billing.Monthly': 'Monthly',
            'billing.Yearly':  'Annually'
        },
        plan_wrappers: {
            // Renders only if value is defined and not null.
            '{submit}':'<input type="submit" value="Buy Now">',
            '{product.thumbnail_url}':'<a{product.url}><img class="plan_product_thumbnail" alt="{HTML:name}" src="{URL:product.thumbnail_url}"></a>',
            '{product.image_url}':'<div class="plan_product_image"><a{product.url}><img alt="{HTML:name}" src="{URL:product.image_url}"></a></div>',
            '{product.url}': ' href="{URL:product.url}" target="_blank" ',
            '{product.description_long}': '<button class="description_long" form="decoy">more...</button><div>{product.description_long}</div>'
        },
    });

    // Addon & Plan Classes:

    app.Plan = function(record, target){
        app.Sellable.call(this, record, 'plan');
        app.plans[record.pp_plan_id] = this; // enumerate it
        // events link:
        target.plan = this;
        this.target = target;
        this.addon_list =[];
        this.optionPrefix='plan[options][';
        this.optionPostfix=']';

        this.registerEvents = function(shared){
            this.registerTotalEvent();
            this.registerGrandTotalEvent(shared);
            this.registerSubmitEvent();
        };
        this.registerSubmitEvent = function(){
            var target = this.target;
            // usr `live` bind on parent
            $(target).on('click','[type=submit]',function(){
                $('input',target).each(function(){
                    if (this.value==="0") this.disabled = "disabled";
                });

                if (app.debug) {
                    alert("Purchase Request:\n" + decodeURIComponent(jQuery(target).serialize()).replace(/&/g, "\n"));
                    console.log("Purchase:", jQuery(target).serializeArray());
                    $('input',target).each(function(){
                        if (this.value==="0") this.disabled = undefined;
                    });/**/
                } else {
                    console.log('TODO: Subscription sales event');
                }
            });
        };
        this.registerTotalEvent = function() {
            var target = this.target;
            // use `live` bind on parent
            $(target).on("change keyup", ".plan_quantity input", function(){
                target.plan.updateTotal();
            }).trigger('change');
        };
        this.updateTotal = function (element) {
            var row = this.row,
                pricing = this.pricing,
                target = this.target,
                self = this;
            row.quantity = $(".plan_quantity input", target).val();
            self.reCalc();
            row.submit = (row.total === 0) ? null : 1; // removes submit
            app.fillTemplate('plan_amount', $(".plan_amount", target), [row, pricing]);
        };
        this.registerGrandTotalEvent = function(shared){
            target = this.target;
            // use `live` bind on parent
            $(target).on('change keyup',".plan_quantity input, .addon_quantity input", function(){
                target.plan.updateGrandTotal(shared);
            }).trigger('change');
        };
        this.updateGrandTotal = function(shared){
            var row = this.row,
                pricing = this.pricing,
                target = this.target,
                amounts=[row.total];
            $(".addon_amount .amount", target).each(function () {
                amounts.push(this.innerHTML);
            });
            row.grand_total = app.add_currency(amounts);
            app.fillTemplate("grand_total", $(".grand_total", target),
                shared ? [row, pricing, shared] : [row, pricing]
            );
        };
        this.renderDetails = function(){
            app.fillTemplate('plan_details', $('.plan_details',this.target), [R.plan_wrappers,this.row]);
            app.fillTemplate('plan_image', $('.plan_image',this.target), [R.plan_wrappers,this.row]);
            app.fillTemplate('plan_billing_cycle', $('.billing_cycle',this.target), [R.plan_wrappers,this.row]);
        };
        this.renderQuantity = function(){
            app.fillTemplate('plan_quantity', $(".plan_quantity", this.target), this.row);
        };
        this.renderAddons = function(shared){
            app.multiData('addon_selection', $('.onecrm-p-a-addons', this.target), {
                'list': this.addon_list,
                'shared': shared ? shared : {}
            });
        };

        var addons = this.row.addons, addon;
        if (addons) for (addon in addons) {
            if (R.Addon[addons[addon]]) {
                this.addon_list.push(R.Addon[addons[addon]]);
            }
        }
    };
    app.Addon = function(record, target){
        app.Sellable.call(this, record, 'addon');
        app.addons[record.pp_addon_id] = this; // enumerate it
        this.target = target;
        this.optionPrefix='addon[options][';
        this.optionPostfix=']';


        this.renderQuantity = function(){
            app.fillTemplate('addon_quantity', $(".addon_quantity", this.target), this.row);
        };

        this.registerEvents = function(){
            var target = this.target;
            $('.addon_quantity input', target).on('change keyup', function (e) {
                var self = target.addon,
                    row = self.row,
                    pricing = self.pricing;
                row.quantity = this.value;
                self.reCalc();
                app.fillTemplate('addon_amount', $(".addon_amount", target), [row, pricing]);
            }).trigger('change');

        }

    };

    app.Sellable = function (record, type) {
        if (type !== "plan" && type !== "addon") throw new Error('unsupported Sellable type');
        this.type = type;
        this.row = $.extend(true,{} , record); // prevent modifications by reference
        this.optionList=[];
        this.optionControls={};
        this.optionPrefix='options[';
        this.optionPostfix=']';

        this.buildOptionList = function(){
            var options = this.row.options, option;
            if (options) for (option in options) {
                if (R.Option[options[option]]) {
                    this.optionList.push(R.Option[options[option]]);
                }
            }
            return this;
        };
        this.buildOptionControls = function(data){
            if (this.optionList.length < 1 ) this.buildOptionList();
            this.optionControls = app.renderAllControls(this.optionList,data,this.optionPrefix,this.optionPostfix);
            return this;
        };
        this.renderOptionSet = function(template, data){
            if (! template) template = "all_options";
            this.buildOptionControls(data);
            app.fillTemplate(template,$(".options",this.target),this.optionControls);
        };
        if (this.row.qty_min|0 < 1) this.row.qty_min = (type === "plan") ? 1 : 0; // plans can't have zero units
        if (this.row.qty_max|0 < 1) this.row.qty_max = Infinity;
        if (this.row.quantity|0 < 1) this.row.quantity = this.row.qty_min;
        // convert row.pricing_tiers to something more edible:
        var i, prev_index = -1, tiers=[], print_tiers=[];
        if (!this.row.pricing_tiers) this.row.pricing_tiers={1:this.row.amount};
        for (i in this.row.pricing_tiers) {
            tiers.push({'quantity': i, 'amount': this.row.pricing_tiers[i]});
            print_tiers.push({'quantity': i, 'amount': app.format_currency(this.row.pricing_tiers[i],true)});
            if (prev_index > -1){
                tiers[prev_index]['limit'] = print_tiers[prev_index]['limit'] = tiers[prev_index+1].quantity-1;
            }
            prev_index++;
        }
        tiers[prev_index]['limit'] = this.row.qty_max;
        print_tiers[prev_index]['limit'] = (this.row.qty_max === Infinity) ? R.translations['lang.Infinity'] : this.row.qty_max;

        // gather the pricing data in one place:
        var currency = this.row.currency.split(': ');
        this.pricing = {
            'model': this.row.pricing_model,
            'tiers': tiers,
            'print_tiers': print_tiers,
            'min': this.row.qty_min,
            'max': this.row.qty_max,
            'currency_symbol': currency[1],
            'currency_name': currency[0],
            'currency_iso4217': this.row.currency_code
        };

        // pricing models: https://www.chargebee.com/docs/addons.html#pricing-attributes
        // Flat Fee has no quantity
        // Per Unit pricing is the same for all quantities
        // Volume pricing has a price for all units based on quantity targets
        // Tiered pricing has an aggregated total of prices for units within quantity targets
        // Stairstep has set prices for quantities within targets

        this.reCalc = function(){
            var total = 0, tiers=this.pricing.tiers;
            quantity = this.row.quantity|0;
            if (quantity===undefined) {
                console.log('WARN: reCalc called with undefined quantity ', this.row);
            } else switch(this.pricing.model){
                case 'Per Unit':
                    total = this.row.amount * quantity;
                    break;
                case 'Volume':
                    for (var i in tiers){
                        if (quantity >= tiers[i].quantity) total = tiers[i].amount * quantity;
                    }
                    break;
                case 'Tiered':
                    for (var i in tiers) {
                        if (quantity <= tiers[i].limit) {
                            total += tiers[i].amount * (quantity - tiers[i].quantity + 1);
                            break;
                        } else {
                            total += tiers[i].amount * (tiers[i].limit - tiers[i].quantity + 1);
                        }
                    }
                    break;
                case 'Stairstep':
                    var stair_total;
                    total = 0;
                    for (i in tiers){
                        if (quantity >= tiers[i].quantity){
                            total = tiers[i].amount;
                        } else break;
                    }
                    break;
                default: console.log('unhandled pricing model:', this.pricing.model);
            }
            // TODO: take number formatting from 1CRM locale settings.
            this.row.total = app.format_currency(total);
            return this;
        };
        this.renderPricingModel = function(shared){
            var row = this.row, pricing = this.pricing, target = this.target;
            if (pricing.model !== "flat_fee") {
                app.fillTemplate('plan_pricing', $('.plan_pricing', target),
                    [row, pricing, shared ? shared : {}]
                );
                app.multiData('pricing_rows_' + row.pricing_model, $(".plan_tiers", target), {
                    list: pricing.print_tiers,
                    shared: [pricing, row, shared ? shared : {}]
                });
            }
        };

    };

    //Filter Form
    app.FilterForm = function () {};
    app.FilterForm.submit = function (resetPage) {
        var form = $('#list-filter-form');

        if (resetPage) {
            var action = form.attr('action');
            action = action.replace(new RegExp("page_number=\\d", "g"), '');
            action = action.replace(new RegExp("[&]*$"),'');
            action = action.replace(new RegExp("[?]*$"),'');
            form.attr('action', action);
        }

        form.submit();
        return true;
    }

    //Pagination
    app.Pagination = function () {};
    app.Pagination.load = function (pageUrl) {
        var form = $('#list-filter-form');

        if (form.length > 0) {
            form.attr('action', pageUrl);
            app.FilterForm.submit(false);
        } else {
            window.location.href = pageUrl
        }
        return false;
    }

    // templating
    app.copyTemplate = function (src, dest) {
        if (app.debugVerbose) console.log("copyTemplate: %s,%s", src, dest);
        $("#" + dest).html(app.templates[src]);
    };

    // takes an array of full arguments to app.multiData() or app.fillTemplate()
    app.multiTemplate = function (list, fillTemplate) {
        for (var i in list) {
            if (fillTemplate) app.fillTemplate.apply(this,list[i]);
            else app.multiData.apply(this, list[i]);
        }
    };
    // copy app.templates[src] to dest with replacements {} or [{},...]
    app.fillTemplate = function (src, dest, data, leave_unreplaced) {
        var target;
        target = (typeof dest === "string") ? ("#"+dest) : dest;
        if (!app.templates[src]) {
            app.debug && console.log('fillTemplate: template %s missing', src);
            return false;
        }
        if (app.debugVerbose || app.debugReplace)
            console.log('fillTemplate: src:',src, 'dest:',dest, 'data:',data,')');
        target.html(app.replaceAssoc(app.templates[src], data, leave_unreplaced));
        try { /**/
            // run matching tScripts init if it exists:
            app.tScripts[src] && app.tScripts[src].init && app.tScripts[src].init.apply(this, arguments, OneCRM.Portal.App);
        } catch (err) {
            if (app.message==="Fatal") throw err;
            app.showError("fillTemplate: error in template script",err);
        }/**/
    };
    // replace dest with multiple data formatted by single src template.
    app.multiData = function (src, dest, list, leave_unreplaced) {
        var id, dom, target, shared = [{}], args, tpl = app.templates[src];
        target = (typeof dest == "string")? ("#"+dest) : dest;
        try {
            if (typeof list === "string") list = eval('(' + list + ')'); // ie "R.Module.list"
        } catch(e){
            app.showError('multiData: bad list',e);
        }
        app.debugVerbose && console.log('multiData src:', src, 'dest:', dest, 'list:', list);
        if (!tpl) {
            app.debug && console.log('multiData: template %s missing', src);
            return false;
        }
        if (list.shared && list.list) {
            shared = list.shared;
            list = list.list;
        }
        if (!Array.isArray(shared)) shared = [shared];
        try { /**/
            for (var i in list) {
                args = list[i];
                if (!Array.isArray(args)) args = [args];
                args = args.concat(shared);
                dom = $(target).append(app.replaceAssoc(tpl, args, leave_unreplaced))
                    [0].lastElementChild;
                if (app.tScripts[src] && app.tScripts[src].init) {
                    app.tScripts[src].init.apply(this, [src, dom, args, leave_unreplaced]);
                }
            }
        } catch (err) {
            if (app.message==="Fatal") throw err;
            app.showError("multidata: error in template script", err);
        }/**/
    };

    app.replaceFilters = {
        'HTML': function(rep){return rep.replace(/[<>&'"]/g,function(r){return"&#"+r.charCodeAt(0)+";";});},
        'URL': function(rep){return encodeURI(rep);},
        'ENC': function(rep){return encodeURIComponent(rep);},
        'TPL': function(rep){
            if (!app.templates[rep]) app.log("warning","missing TPL: " + rep);
            return app.templates[rep] || "";
        }
    };
    /* replace {keys} with key-value pairs in data {} or [{},...] or for {wrappers},
     * replace {keys} with value replaced into data[{key}] if that exists */
    app.replaceAssoc = function (text, data, leave_unreplaced) {
        var count, replace, wrappers, match;
        var re = /{[a-zA-Z][^{}]+}/g;
        if (text === null || text === undefined ) {
            console.log("WARN: replaceAssoc(", text, data, ')');
            return text;
        }
        try {
            if (typeof data === "string") data = eval('(' + data + ')');
        } catch(e) {
            if (app.message==="Fatal") throw err;
            app.showError('replaceAssoc: bad data', e);
        }
        if (!Array.isArray(data)) data = [data];
        R.translations && data.push(R.translations);
        for (var j = 1; j < 16; j++) {
            count = 0;
            wrappers = {length: 0};
            for (i in data) {
                try {
                    if (typeof data[i] === 'string') data[i] = eval('(' + data[i] + ')');
                } catch (e) {
                    app.showError('replaceAssoc: bad data index "'+i+'"',e);
                }
                replace = data[i];
                // look for wrappers first:
                while ((match = re.exec(text))) {
                    var key = match[0];
                    if (replace[key] !== undefined && replace[key] !== null) {
                        wrappers[key] = replace[key];
                        wrappers.length++;
                    }
                }
            }
            for (i in data) {
                replace = data[i];
                app.debugReplace && console.log("replaceAssoc:", replace);
                text = text.replace(re, function (key) {
                    var quote = false,
                        _key=key.substr(1, key.length - 2),
                        filter;
                    [_key, filter] = _key.split(':').reverse();
                    var rep = (filter!=="TPL") ? replace[_key]: _key;
                    if (rep === undefined || rep === null ) {
                        app.debugReplace && console.log("skipping key %s", _key);
                        return key;
                    } else count++;
                    if (filter && app.replaceFilters[filter]) {
                        rep = app.replaceFilters[filter](rep.toString());
                    }
                    if (wrappers[key] && rep!=='') rep = wrappers[key].replace(key, rep);
                    app.debugReplace && console.log("replacing key %s with %s", _key, rep);
                    return rep;
                });
            }
            if (count === 0) break;
        }
        if (app.debugReplace && app.debugVerbose) console.log("replaceAssoc result= ", text);
        else if (!(leave_unreplaced || app.force_leave_unreplaced)) text = text.replace(/{[a-zA-Z][^{}]+}/g, '');

        if (app.replaceProtocolRelative) {
            text = text.replace(/"https?:\/\//ig,'"//');
        }
        return text;
    };

    // converts {a:{b:{c:1}}} to {'a.b.c':1};
    app.flatten_keys = function (ref,root,separator){
        if ( typeof separator !== 'string' ) separator = '.';
        root = (typeof root === 'string' ) ? root + separator : '';
        var out={};
        for (var i in ref) {
            if (typeof ref[i] !== 'object'){
                out[root + i] = ref[i];
            } else {
                $.extend(out,app.flatten_keys(ref[i],root + i, separator));
            }
        }
        return out;
    };

    // Form building:

    // these include themselves only when the property is not empty:
    app.propertyWrappers = {
        '{type}':       ' type="{HTML:type}"',
        '{name}':       ' name="{HTML:name}"',
        '{length}':     ' maxlength="{HTML:length}"',
        '{size}':       ' size="{HTML:size}"',
        '{key}':        ' value="{HTML:key}"', /* for option */
        '{value}':      ' value="{HTML:value}"',
        '{min}':        ' min="{HTML:min}"',
        '{max}':        ' max="{HTML:max}"',
        '{inputmode}':  ' inputmode="{HTML:inputmode}"',
        '{rows}':       ' rows="{HTML:rows}"',
        '{cols}':       ' cols="{HTML:cols}"',
        '{step}':       ' step="{HTML:step}"',
        '{pattern}':    ' pattern="{HTML:pattern}"',
        '{class}':      ' class="{HTML:class}"',
        '{style}':      ' style="{HTML:style}"',
        '{height}':     ' height="{HTML:height}"',
        '{width}':      ' width="{HTML:width}"',
        '{alt}':        ' alt="{HTML:alt}"',
        '{title}':      ' title="{HTML:title}"',
        '{placeholder}':' placeholder="{HTML:placeholder}"',
        '{tabindex}':   ' tabindex="{HTML:tabindex}"',
        '{src}':        ' src="{URL:src}"',
        '{href}':       ' href="{URL:href}"',
        '{formaction}': ' formaction="{URL:formaction}"',
        '{formmethod}': ' formmethod="{HTTP:formmethod}"',
        '{formtarget}': ' formtarget="{HTTP:formtarget}"',
        '{formenctype}':' formenctype="{HTTP:formenctype}"',
        '{formnovalidate}':' formnovalidate',
        '{required}':   ' required',
        '{selected}':   ' selected',
        '{checked}':    ' checked',
        '{disabled}':   ' disabled',
        '{readonly}':   ' readonly',
        '{autofocus}':  ' autofocus',
        '{multiple}':   ' multiple',
    };
    app.controlWrappers = {
        '{tip}':        '<mark class="tip"><span>{description}</span></mark>',
        '{label}':      '<span>{label}</span>',
    };
    app.controlTemplates = {
        'input':    '<label{class}{title}>{tip}<input{type}{name}{class}{value}{length}{placeholder}{required}{pattern}{inputmode}{min}{max}>{label}</label>',
        'select':   '<label{class}{title}>{tip}<select{name}{class}{multiple}{required}><option value="" disabled selected>{HTML:placeholder}</option>{optgroups}</select><span>{label}</span></label>',
        'option':   '<option{key}{selected}>{HTML:value}</option>',
        'textarea': '<label{class}{title}>{tip}<span>{label}</span><textarea{type}{name}{length}{placeholder}{required}>{HTML:value}</textarea></label>'
    };
    app.controlMap = {
        varchar:    {tpl: 'input', class: 'varchar', type: 'text'},
        bool:       {tpl: 'input', class: 'bool', type: 'checkbox'},
        number:     {tpl: 'input', class: 'number', type: 'number', inputmode: 'numeric'},
        int:        {tpl: 'input', class: 'int', type: 'number', inputmode: 'numeric'},
        phone:      {tpl: 'input', class: 'phone', type: 'text',  inputmode: 'tel'},
        email:      {tpl: 'input', class: 'email', type: 'email', inputmode: 'email'},
        url:        {tpl: 'input', class: 'url', type: 'url', inputmode: 'url'},
        file_ref:   {tpl: 'input', class: 'file', type: 'file'},
        date:       {tpl: 'input', class: 'date', type: 'date'},
        datetime:   {tpl: 'input', class: 'datetime', type: 'datetime-local'},
        enum:       {tpl: 'select', class: 'enum', multiple: ''},
        multienum:  {tpl: 'select', class: 'multienum', multiple: 1},
        currency:   {tpl: 'select', class: 'currency', multiple: ''},
        html:       {tpl: 'textarea', class: 'html'},
    };
    app.controlDefaults = {
        currencyOptions:{
            USD: 'US Dollar', CAD: 'Canadian Dollar', EUR: 'Euro', GBP: 'UK Pound', AUD: 'Australian Dollar'
        },
        countryOptions:{
            US: 'United States', CA: 'Canada', EU: 'Europe', GB: 'Great Britain', AU: 'Australia'
        }
    };
    // generate options using a meta field and optional data
    app.renderOptions = function(meta, data){
        var i, options = meta.options;

        if (typeof options == "string"){
            options = options.replace(/[\r\n]+/g,"\n").split("\n");
            for (i in options){
                parts = options[i].replace(/ = /,'^,^').split('^,^',2);
                options[i] = {key:parts[0], value: (parts[1]!== undefined) ? parts[1] : parts[0]};
            }
            meta.options = options;
        }
        if (typeof data == 'string'){
            if ((meta.type && meta.type === 'multienum') || meta.multiple ){
                data = data.split('^,^');
            } else data=[data];
        }
        var copy={};
        for (i in options) copy[options[i].key]=options[i];
        for (i in data) {
            if (copy[data[i]]) copy[data[i]]['selected'] = 1;
            else copy[data[i]] = {selected: 1, key: data[i], value: data[i]};
        }
        var out={};
        for (i in copy) {
            out[i] = app.replaceAssoc(app.controlTemplates['option'], [
                copy[i], app.propertyWrappers
            ]);
        }
        return Object.values(out).join("\n");
    };
    // generate HTML control for a meta field with optional data value
    app.renderControl = function (meta, data, prefix, postfix) {
        var type = 'varchar';
        if (meta && meta.type && app.controlMap[meta.type]) type = meta.type;

        if (data === '' || data === undefined || data === null) data = meta.default;
        if (data === '') data = undefined;
        var prop = {'value': data};
        switch (type) {
            case 'bool':
                prop.checked = (data && data !== "false") ? 1 : '';
                break;
            case 'currency':
                if (meta.options === '' || meta.options === undefined || meta.options === null)
                    meta.options = app.controlDefaults.currencyOptions;
            /** fall through */
            case 'enum':
            case 'multienum':
                prop.optgroups = app.renderOptions(meta, data);
                break;
        }
        prop.required = +meta.required || '';
        prop.type = app.controlMap[type].type;
        prop.title = meta.tip ? '': meta.description;
        prop.class = [app.controlMap[type].class, meta.input];
        if (meta.class) prop.class.push(meta.class);
        prop.class=prop.class.join(" ");
        if (!prefix) prefix='';
        if (!postfix) postfix='';
        prop.name = prefix + meta.input + postfix;
        return app.replaceAssoc(
            app.controlTemplates[app.controlMap[type].tpl], [
                app.propertyWrappers,
                app.controlWrappers,
                $.extend({},app.controlMap[type], meta, prop)
            ]
        );
    };
    // generate keyed list for every control in the metadata
    app.renderAllControls = function(meta, data, prefix, postfix){
        var i,controls={};
        if (!data) data={};

        for (i in meta) {
            if (meta[i] && meta[i].input){
                controls[meta[i].input] = app.renderControl(meta[i], data[meta[i].input], prefix, postfix);
            }
        }

        controls['all_controls'] = Object.values(controls).join("\n");
        return controls;
    };
    // generate keyed list of controls for existing data
    app.renderDataControls = function(meta,data){
        var i, controls={};
        for (i in data) controls[i]=app.renderControl(meta[i],data[i]);
        controls['all_controls'] = Object.values(controls).join("\n");
        return controls;
    };
    // replace controls into template
    app.renderForm = function(template, meta, data, shared, all, leave_unreplaced){
        var controls = all
            ? app.renderAllControls(meta,data)
            : app.renderDataControls(meta,data);
        if (!Array.isArray(shared)) shared = [shared];
        var replacements = [shared.values()].push(controls);
        return app.replaceAssoc(template,replacements,leave_unreplaced);
    };

    // utilities:

    // if round is true-ish , .00 will be removed from numbers >=100
    app.format_currency = function(amount,round){
        var re = (round)? new RegExp(/([0-9]{3})\.00/) : null ;
        return ((+amount).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2})).replace(re,'$1');
    };
    app.add_currency = function(amounts, round){
        var total = 0,converted;
        for (var i in amounts){
            total += +(""+amounts[i]).replace(/,/g,'');
        }
        return app.format_currency(total, round);
    };

    // debugging:

    app.alertErrors = false;    // modal popups - never in production
    app.debug = true;           // console logging
    app.debugReplace = false;   // shows template and individual replacements
    app.debugVerbose = true;    // shows function calls and their args
    app.defaultErrorLevel = 'warning';  // setting this to 'error' will trigger app.die() action
    app.force_leave_unreplaced = false; // leaves template variables that had no matching data record intact
    app.logLevels = {
        'emergency': {'style': 'background:#F00;color:#0FF;font-weight:bold', 'action': ['alert', 'die']},
        'alert': {'style': 'background:#C00;color:#0FF;font-weight:bold', 'action': ['alert', 'die']},
        'critical': {'style': 'background:#800;color:#0FF;font-weight:bold', 'action': ['die']},
        'error': {'style': 'background:#400;color:#0FF;font-weight:bold', 'action': ['die']},
        'warning': {'style': 'background:#FFC;color:#000;font-weight:bold', 'action': []},
        'notice': {'style': 'background:#FFC;color:#008;', 'action': []},
        'info': {'style': 'background:#FFE;color:#008;', 'action': []},
        'debug': {'style': 'background:#FFF;color:#008;', 'action': []},
    };
    app.log = function (level, message, objects) {
        level = level.toString().toLowerCase();
        if (!app.logLevels[level]) level = 'notice';
        if (objects) console.log('%c %s: %s', app.logLevels[level].style, level, message, objects);
        else console.log('%c %s %s', app.logLevels[level].style, level, message);
        var t = this;
        $(app.logLevels[level].action).each(function (i, value) {
            if (value === "alert") {
                alert(level + " error:\n" + message);
            } else if (typeof app[value] == "function") {
                app[value].apply(t, [level, message, objects]);
            } else if (typeof window[value] == "function") {
                window[value].apply(t, [level, message, objects]);
            } else if (typeof value == "function") value.apply(t, [level, message, objects]);
        });
    };
    // called to log runtime errors
    app.showError = function (txt, err) {
        try {
            if (app.debug) app.log(app.defaultErrorLevel, txt, err);
        } catch (err) {
            console.log('catching error: ', err.message);
            if (err.message === "Fatal") throw err;
        }
        if (app.alertErrors) alert(txt + (app.debugVerbose ? "\n" + err.toString() : ""));
    };
    // called for fatal application errors
    app.die = function (level, message) {
        console.log('Fatal error:', message);
        if (level === "emergency") {
            // call the mothership...
        }
        throw new Error('Fatal');
    };

    // bootstrap:

    app.init = function () {
        // parse templates and embedded scripts, remove from page source
        $('.onecrm-p-a script[type="text/template"]').each(function () {
            app.templates[this.id] = this.innerHTML;
        }).remove();
    };

    // some UA's don't implement console logging
    window.console || (window.console={});
    (typeof window.console.log == "function") || (window.console.log = function(){});

})(jQuery); /* end namepace OneCRM.Potal.App */



/**
 * Variable data is expected to be pre-scanned for DOM injection.  Templates only ensure correct structure.
 * See include/util.php onecrm_html_sanitizer_r() and ShortCodes::enqueue_page_data()
 *
 * multiTemplate, multiData, fillTemplate, and replaceAssoc all perform recursive template string replacement
 * All of them can take replacements from a variety of object sources - literal, variable, evaluated.
 * A special case is an object which supplies 'wrappers' - these are stub templates with key = '{key}' which are
 * replaced into the placeholder *before* the value, only if there's a matching key with valid printable value.
 *
 * {HTML:var} quotes special chars <>'"& before inserting var
 * {URL:var} makes an URL safe before inserting it (good for href etc)
 * {ENC:var} quotes var as a URI component
 * {{var}} or {prefix.{var}} inserts a value via indirect reference
 * {HTML:{var}} quotes an indirect reference as expected
 *
 * replaceAssoc examples:
 *
 * var R = OneCRM.Portal.App.replace; // `R` shortcut available within the method closures as well
 * R.wrappers={'{message}':'{greet}, {subject}!', '{golden}':'Silence is {golden}'};
 * R.replace={'golden':'golden'};
 * app.replaceAssoc('{golden}',[R.replace,R.wrappers]);
 * -> "Silence is golden" // golden is substituted into the wrapper itself, so it finishes in 1 pass
 *
 * R.replace1={'greet':'Hello', 'subject':'World', 'message':1};
 * app.replaceAssoc('{message}','[R.wrappers,R.replace1]'); // replacements will be eval'ed
 * -> "{greet}, {subject}!"
 * -> "Hello, World!"
 *
 * R.replace1.message=null; // turns off conditional include of the {message} wrapper
 * app.replaceAssoc('{message}','[R.wrappers,R.replace1]');
 * -> ""
 *
 * multiData can have {'list':[row1,row2,...],'shared':[{'{key}':'stub {template}',...}]}
 * in which case the 'shared' apply to every row, good use of shared values and wrappers
 */
