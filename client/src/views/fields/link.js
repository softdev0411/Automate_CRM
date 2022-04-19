/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

define('views/fields/link', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'link',

        listTemplate: 'fields/link/list',

        detailTemplate: 'fields/link/detail',

        editTemplate: 'fields/link/edit',

        searchTemplate: 'fields/link/search',

        nameName: null,

        idName: null,

        foreignScope: null,

        selectRecordsView: 'views/modals/select-records',

        autocompleteDisabled: false,

        createDisabled: false,

        searchTypeList: ['is', 'isEmpty', 'isNotEmpty', 'isNot', 'isOneOf', 'isNotOneOf'],

        selectFilterList: null,

        data: function () {
            var nameValue = this.model.has(this.nameName) ?
                this.model.get(this.nameName) :
                this.model.get(this.idName);

            if (nameValue === null) {
                nameValue = this.model.get(this.idName);
            }

            if (this.isReadMode() && !nameValue && this.model.get(this.idName)) {
                nameValue = this.translate(this.foreignScope, 'scopeNames');
            }

            var iconHtml = null;

            if (this.mode === 'detail') {
                iconHtml = this.getHelper().getScopeColorIconHtml(this.foreignScope);
            }

            return _.extend({
                idName: this.idName,
                nameName: this.nameName,
                idValue: this.model.get(this.idName),
                nameValue: nameValue,
                foreignScope: this.foreignScope,
                valueIsSet: this.model.has(this.idName),
                iconHtml: iconHtml
            }, Dep.prototype.data.call(this));
        },

        getEmptyAutocompleteResult: null,

        getSelectFilters: function () {},

        getSelectBoolFilterList: function () {
            return this.selectBoolFilterList;
        },

        getSelectPrimaryFilterName: function () {
            return this.selectPrimaryFilterName;
        },

        getSelectFilterList: function () {
            return this.selectFilterList;
        },

        getCreateAttributes: function () {},

        setup: function () {
            this.nameName = this.name + 'Name';
            this.idName = this.name + 'Id';

            this.foreignScope = this.options.foreignScope || this.foreignScope;

            this.foreignScope = this.foreignScope ||
                this.model.getFieldParam(this.name, 'entity') || this.model.getLinkParam(this.name, 'entity');

            if ('createDisabled' in this.options) {
                this.createDisabled = this.options.createDisabled;
            }

            if (this.mode !== 'list') {
                this.addActionHandler('selectLink', () => {
                    this.notify('Loading...');

                    var viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') ||
                        this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode !== 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                        mandatorySelectAttributeList: this.mandatorySelectAttributeList,
                        forceSelectAllAttributes: this.forceSelectAllAttributes,
                        filterList: this.getSelectFilterList(),
                    }, (view) => {
                        view.render();

                        this.notify(false);

                        this.listenToOnce(view, 'select', (model) => {
                            this.clearView('dialog');

                            this.select(model);
                        });
                    });
                });

                this.addActionHandler('clearLink', () => {
                    this.clearLink();
                });
            }

            if (this.mode === 'search') {
                this.addActionHandler('selectLinkOneOf', () => {
                    this.notify('Loading...');

                    var viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') ||
                        this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode !== 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        multiple: true,
                    }, (view) => {
                        view.render();
                        this.notify(false);

                        this.listenToOnce(view, 'select', (models) => {
                            this.clearView('dialog');

                            if (Object.prototype.toString.call(models) !== '[object Array]') {
                                models = [models];
                            }

                            models.forEach((model) => {
                                this.addLinkOneOf(model.id, model.get('name'));
                            });
                        });
                    });
                });

                this.events['click a[data-action="clearLinkOneOf"]'] = function (e) {
                    var id = $(e.currentTarget).data('id').toString();

                    this.deleteLinkOneOf(id);
                };
            }
        },

        select: function (model) {
            this.$elementName.val(model.get('name') || model.id);
            this.$elementId.val(model.get('id'));

            if (this.mode === 'search') {
                this.searchData.idValue = model.get('id');
                this.searchData.nameValue = model.get('name') || model.id;
            }

            this.trigger('change');
        },

        clearLink: function () {
            this.$elementName.val('');
            this.$elementId.val('');
            this.trigger('change');
        },

        setupSearch: function () {
            this.searchData.oneOfIdList = this.getSearchParamsData().oneOfIdList ||
                this.searchParams.oneOfIdList || [];

            this.searchData.oneOfNameHash = this.getSearchParamsData().oneOfNameHash ||
                this.searchParams.oneOfNameHash || {};

            if (~['is', 'isNot', 'equals'].indexOf(this.getSearchType())) {
                this.searchData.idValue = this.getSearchParamsData().idValue ||
                    this.searchParams.idValue || this.searchParams.value;

                this.searchData.nameValue = this.getSearchParamsData().nameValue ||
                    this.searchParams.nameValue || this.searchParams.valueName;
            }

            this.events = _.extend({
                'change select.search-type': function (e) {
                    var type = $(e.currentTarget).val();
                    this.handleSearchType(type);
                },
            }, this.events || {});
        },

        handleSearchType: function (type) {
            if (~['is', 'isNot', 'isNotAndIsNotEmpty'].indexOf(type)) {
                this.$el.find('div.primary').removeClass('hidden');
            }
            else {
                this.$el.find('div.primary').addClass('hidden');
            }

            if (~['isOneOf', 'isNotOneOf', 'isNotOneOfAndIsNotEmpty'].indexOf(type)) {
                this.$el.find('div.one-of-container').removeClass('hidden');
            }
            else {
                this.$el.find('div.one-of-container').addClass('hidden');
            }
        },

        getAutocompleteMaxCount: function () {
            if (this.autocompleteMaxCount) {
                return this.autocompleteMaxCount;
            }

            return this.getConfig().get('recordsPerPage');
        },

        getAutocompleteUrl: function () {
            var url = this.foreignScope + '?maxSize=' + this.getAutocompleteMaxCount();

            if (!this.forceSelectAllAttributes) {
                var select = ['id', 'name'];

                if (this.mandatorySelectAttributeList) {
                    select = select.concat(this.mandatorySelectAttributeList);
                }

                url += '&select=' + select.join(',');
            }

            var boolList = this.getSelectBoolFilterList();


            if (boolList) {
                url += '&' + $.param({'boolFilterList': boolList});
            }

            var primary = this.getSelectPrimaryFilterName();

            if (primary) {
                url += '&' + $.param({'primaryFilter': primary});
            }

            return url;
        },

        afterRender: function () {
            if (this.mode === 'edit' || this.mode === 'search') {
                this.$elementId = this.$el.find('input[data-name="' + this.idName + '"]');
                this.$elementName = this.$el.find('input[data-name="' + this.nameName + '"]');

                this.$elementName.on('change', () => {
                    if (this.$elementName.val() === '') {
                        this.$elementName.val('');
                        this.$elementId.val('');

                        this.trigger('change');
                    }
                });

                this.$elementName.on('blur', e => {
                    setTimeout(() => {
                        if (this.mode === 'edit' && this.model.has(this.nameName)) {
                            e.currentTarget.value = this.model.get(this.nameName);
                        }
                    }, 100);

                    if (!this.autocompleteDisabled) {
                        setTimeout(() => this.$elementName.autocomplete('clear'), 300);
                    }
                });

                var $elementName = this.$elementName;

                if (!this.autocompleteDisabled) {
                    this.$elementName.autocomplete({
                        beforeRender: $c => {
                            if (this.$elementName.hasClass('input-sm')) {
                                $c.addClass('small');
                            }
                        },
                        serviceUrl: q => {
                            return this.getAutocompleteUrl(q);
                        },
                        lookup: (q, callback) => {
                            if (q.length === 0) {
                                if (this.getEmptyAutocompleteResult) {
                                    callback(
                                        this._transformAutocompleteResult(this.getEmptyAutocompleteResult())
                                    );
                                }

                                return;
                            }

                            Espo.Ajax
                                .getRequest(this.getAutocompleteUrl(q), {q: q})
                                .then(response => {
                                    callback(this._transformAutocompleteResult(response));
                                });
                        },
                        minChars: 0,
                        triggerSelectOnValidInput: false,
                        autoSelectFirst: true,
                        noCache: true,
                        formatResult: suggestion => {
                            return this.getHelper().escapeString(suggestion.name);
                        },
                        onSelect: (s) => {
                            this.getModelFactory().create(this.foreignScope, (model) => {
                                model.set(s.attributes);

                                this.select(model);
                            });
                        },
                    });

                    this.$elementName.off('focus.autocomplete');

                    this.$elementName.on('focus', () => {
                        if (this.$elementName.val()) {
                            this.$elementName.get(0).select();

                            return;
                        }

                        this.$elementName.autocomplete('onFocus');
                    });

                    this.$elementName.attr('autocomplete', 'espo-' + this.name);

                    this.once('render', () => {
                        $elementName.autocomplete('dispose');
                    });

                    this.once('remove', () => {
                        $elementName.autocomplete('dispose');
                    });

                    if (this.mode === 'search') {
                        var $elementOneOf = this.$el.find('input.element-one-of');

                        $elementOneOf.autocomplete({
                            serviceUrl: q => {
                                return this.getAutocompleteUrl(q);
                            },
                            minChars: 1,
                            paramName: 'q',
                            noCache: true,
                            formatResult: suggestion => {
                                return this.getHelper().escapeString(suggestion.name);
                            },
                            transformResult: response => {
                                var response = JSON.parse(response);

                                return this._transformAutocompleteResult(response);
                            },
                            onSelect: s => {
                                this.addLinkOneOf(s.id, s.name);

                                $elementOneOf.val('');
                            },
                        });

                        $elementOneOf.attr('autocomplete', 'espo-' + this.name);

                        this.once('render', () => {
                            $elementOneOf.autocomplete('dispose');
                        });

                        this.once('remove', () => {
                            $elementOneOf.autocomplete('dispose');
                        });

                        this.$el.find('select.search-type').on('change', () => {
                            this.trigger('change');
                        });
                    }
                }

                $elementName.on('change', () => {
                    if (this.mode !== 'search' && !this.model.get(this.idName)) {
                        $elementName.val(this.model.get(this.nameName));
                    }
                });
            }

            if (this.mode === 'search') {
                var type = this.$el.find('select.search-type').val();

                this.handleSearchType(type);

                if (~['isOneOf', 'isNotOneOf', 'isNotOneOfAndIsNotEmpty'].indexOf(type)) {
                    this.searchData.oneOfIdList.forEach((id) => {
                        this.addLinkOneOfHtml(id, this.searchData.oneOfNameHash[id]);
                    });
                }
            }
        },

        _transformAutocompleteResult: function (response) {
            let list = [];

            response.list.forEach(item => {
                list.push({
                    id: item.id,
                    name: item.name || item.id,
                    data: item.id,
                    value: item.name || item.id,
                    attributes: item,
                });
            });

            return {suggestions: list};
        },

        getValueForDisplay: function () {
            return this.model.get(this.nameName);
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.idName) == null) {
                    var msg = this.translate('fieldIsRequired', 'messages')
                        .replace('{field}', this.getLabelText());

                    this.showValidationMessage(msg);

                    return true;
                }
            }
        },

        deleteLinkOneOf: function (id) {
            this.deleteLinkOneOfHtml(id);

            var index = this.searchData.oneOfIdList.indexOf(id);

            if (index > -1) {
                this.searchData.oneOfIdList.splice(index, 1);
            }

            delete this.searchData.oneOfNameHash[id];

            this.trigger('change');
        },

        addLinkOneOf: function (id, name) {
            if (!~this.searchData.oneOfIdList.indexOf(id)) {
                this.searchData.oneOfIdList.push(id);
                this.searchData.oneOfNameHash[id] = name;
                this.addLinkOneOfHtml(id, name);

                this.trigger('change');
            }
        },

        deleteLinkOneOfHtml: function (id) {
            this.$el.find('.link-one-of-container .link-' + id).remove();
        },

        addLinkOneOfHtml: function (id, name) {
            id = Handlebars.Utils.escapeExpression(id);

            name = this.getHelper().escapeString(name);

            var $container = this.$el.find('.link-one-of-container');

            var $el = $('<div />').addClass('link-' + id).addClass('list-group-item');

            $el.html(name + '&nbsp');

            $el.prepend(
                '<a href="javascript:" class="pull-right" data-id="' + id + '" ' +
                'data-action="clearLinkOneOf"><span class="fas fa-times"></a>'
            );

            $container.append($el);

            return $el;
        },

        fetch: function () {
            var data = {};

            data[this.nameName] = this.$el.find('[data-name="'+this.nameName+'"]').val() || null;
            data[this.idName] = this.$el.find('[data-name="'+this.idName+'"]').val() || null;

            return data;
        },

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val();
            var value = this.$el.find('[data-name="' + this.idName + '"]').val();

            if (~['isOneOf', 'isNotOneOf'].indexOf(type) && !this.searchData.oneOfIdList.length) {
                return {
                    type: 'isNotNull',
                    attribute: 'id',
                    data: {
                        type: type,
                    },
                };
            }

            if (type === 'isEmpty') {
                var data = {
                    type: 'isNull',
                    attribute: this.idName,
                    data: {
                        type: type
                    }
                };

                return data;
            }

            if (type === 'isNotEmpty') {
                var data = {
                    type: 'isNotNull',
                    attribute: this.idName,
                    data: {
                        type: type,
                    },
                };

                return data;
            }

            if (type === 'isOneOf') {
                var data = {
                    type: 'in',
                    attribute: this.idName,
                    value: this.searchData.oneOfIdList,
                    data: {
                        type: type,
                        oneOfIdList: this.searchData.oneOfIdList,
                        oneOfNameHash: this.searchData.oneOfNameHash,
                    },
                };

                return data;
            }

            if (type === 'isNotOneOf') {
                var data = {
                    type: 'or',
                    value: [
                        {
                            type: 'notIn',
                            attribute: this.idName,
                            value: this.searchData.oneOfIdList,
                        },
                        {
                            type: 'isNull',
                            attribute: this.idName,
                        },
                    ],
                    data: {
                        type: type,
                        oneOfIdList: this.searchData.oneOfIdList,
                        oneOfNameHash: this.searchData.oneOfNameHash,
                    }
                };

                return data;
            }

            if (type === 'isNotOneOfAndIsNotEmpty') {
                var data = {
                    type: 'notIn',
                    attribute: this.idName,
                    value: this.searchData.oneOfIdList,
                    data: {
                        type: type,
                        oneOfIdList: this.searchData.oneOfIdList,
                        oneOfNameHash: this.searchData.oneOfNameHash,
                    },
                };

                return data;
            }

            if (type === 'isNot') {
                if (!value) {
                    return false;
                }

                var nameValue = this.$el.find('[data-name="' + this.nameName + '"]').val();

                var data = {
                    type: 'or',
                    value: [
                        {
                            type: 'notEquals',
                            attribute: this.idName,
                            value: value
                        },
                        {
                            type: 'isNull',
                            attribute: this.idName,
                        }
                    ],
                    data: {
                        type: type,
                        idValue: value,
                        nameValue: nameValue,
                    }
                };

                return data;
            }

            if (type === 'isNotAndIsNotEmpty') {
                if (!value) {
                    return false;
                }

                var nameValue = this.$el.find('[data-name="' + this.nameName + '"]').val();

                var data = {
                    type: 'notEquals',
                    attribute: this.idName,
                    value: value,
                    data: {
                        type: type,
                        idValue: value,
                        nameValue: nameValue,
                    },
                };

                return data;
            }

            if (!value) {
                return false;
            }

            var nameValue = this.$el.find('[data-name="' + this.nameName + '"]').val();

            var data = {
                type: 'equals',
                attribute: this.idName,
                value: value,
                data: {
                    type: type,
                    idValue: value,
                    nameValue: nameValue,
                }
            };

            return data;
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront || this.searchParams.type;
        },

    });
});
