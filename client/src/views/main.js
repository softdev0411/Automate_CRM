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

define('views/main', 'view', function (Dep) {

    return Dep.extend({

        scope: null,

        name: null,

        menu: null,

        events: {
            'click .action': function (e) {
                Espo.Utils.handleAction(this, e);
            },
        },

        init: function () {
            this.scope = this.options.scope || this.scope;
            this.menu = {};

            this.options.params = this.options.params || {};

            if (this.name && this.scope) {
                this.menu = this.getMetadata()
                    .get(['clientDefs', this.scope, 'menu',
                        this.name.charAt(0).toLowerCase() + this.name.slice(1)]) || {};
            }

            this.menu = Espo.Utils.cloneDeep(this.menu);

            var globalMenu = {};

            if (this.name) {
                globalMenu = Espo.Utils.cloneDeep(
                    this.getMetadata()
                        .get(['clientDefs', 'Global', 'menu',
                            this.name.charAt(0).toLowerCase() + this.name.slice(1)]) || {}
                );
            }

            ['buttons', 'actions', 'dropdown'].forEach((type) => {
                this.menu[type] = this.menu[type] || [];
                this.menu[type] = this.menu[type].concat(globalMenu[type] || []);

                var itemList = this.menu[type];

                itemList.forEach((item) => {
                    var viewObject = this;

                    if (item.initFunction && item.data.handler) {
                        this.wait(new Promise(resolve => {
                            require(item.data.handler, Handler => {
                                var handler = new Handler(viewObject);

                                handler[item.initFunction].call(handler);

                                resolve();
                            });
                        }));
                    }
                });
            });

            this.updateLastUrl();
        },

        updateLastUrl: function () {
            this.lastUrl = this.getRouter().getCurrentUrl();
        },

        getMenu: function () {
            if (this.menuDisabled) {
                return {};
            }

            var menu = {};

            if (this.menu) {
                ['buttons', 'actions', 'dropdown'].forEach((type) => {
                    (this.menu[type] || []).forEach((item) => {
                        item = Espo.Utils.clone(item);

                        menu[type] = menu[type] || [];

                        if (!Espo.Utils.checkActionAvailability(this.getHelper(), item)) {
                            return;
                        }

                        if (!Espo.Utils.checkActionAccess(this.getAcl(), this.model || this.scope, item)) {
                            return;
                        }

                        if (item.accessDataList) {
                            if (!Espo.Utils.checkAccessDataList(item.accessDataList, this.getAcl(), this.getUser())) {
                                return;
                            }
                        }

                        item.name = item.name || item.action;
                        item.action = item.action || this.name;

                        if (item.labelTranslation) {
                            item.html = this.getHelper().escapeString(
                                this.getLanguage().translatePath(item.labelTranslation)
                            );
                        }

                        menu[type].push(item);
                    });
                });
            }

            return menu;
        },

        getHeader: function () {},

        buildHeaderHtml: function (arr) {
            var a = [];

            arr.forEach(item => {
                a.push('<div class="breadcrumb-item">' + item + '</div>');
            });

            return '<div class="header-breadcrumbs">' +
                a.join('<div class="breadcrumb-separator"><span class="chevron-right"></span></div>') + '</div>';
        },

        getHeaderIconHtml: function () {
            return this.getHelper().getScopeColorIconHtml(this.scope);
        },

        actionShowModal: function (data) {
            var view = data.view;

            if (!view) {
                return;
            };

            this.createView('modal', view, {
                model: this.model,
                collection: this.collection,
            }, view => {
                view.render();

                this.listenTo(view, 'after:save', () => {
                    if (this.model) {
                        this.model.fetch();
                    }

                    if (this.collection) {
                        this.collection.fetch();
                    }
                });
            });
        },

        addMenuItem: function (type, item, toBeginning, doNotReRender) {
            if (item) {
                item.name = item.name || item.action;

                var name = item.name;

                if (name) {
                    var index = -1;

                    this.menu[type].forEach((data, i) => {
                        data = data || {};

                        if (data.name === name) {
                            index = i;

                            return;
                        }
                    });

                    if (~index) {
                        this.menu[type].splice(index, 1);
                    }
                }
            }

            var method = 'push';

            if (toBeginning) {
                method  = 'unshift';
            }

            this.menu[type][method](item);

            if (!doNotReRender && this.isRendered()) {
                this.getView('header').reRender();

                return;
            }

            if (!doNotReRender && this.isBeingRendered()) {
                this.once('after:render', () => {
                    this.getView('header').reRender();
                });
            }
        },

        removeMenuItem: function (name, doNotReRender) {
            var index = -1;
            var type = false;

            ['actions', 'dropdown', 'buttons'].forEach((t) => {
                (this.menu[t] || []).forEach((item, i) => {
                    item = item || {};

                    if (item.name === name) {
                        index = i;
                        type = t;
                    }
                });
            });

            if (~index && type) {
                this.menu[type].splice(index, 1);
            }

            if (!doNotReRender && this.isRendered()) {
                this.getView('header').reRender();

                return;
            }

            if (!doNotReRender && this.isBeingRendered()) {
                this.once('after:render', () => {
                    this.getView('header').reRender();
                });

                return;
            }

            if (doNotReRender && this.isRendered()) {
                this.$el.find('.header .header-buttons [data-name="'+name+'"]').remove();

                return;
            }
        },

        disableMenuItem: function (name) {
            this.$el
                .find('.header .header-buttons [data-name="'+name+'"]')
                .addClass('disabled')
                .attr('disabled');
        },

        enableMenuItem: function (name) {
            this.$el
                .find('.header .header-buttons [data-name="'+name+'"]')
                .removeClass('disabled')
                .removeAttr('disabled');
        },

        actionNavigateToRoot: function (data, e) {
            e.stopPropagation();

            this.getRouter().checkConfirmLeaveOut(() => {
                var options = {
                    isReturn: true,
                };

                var rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;

                this.getRouter().navigate(rootUrl, {trigger: false});
                this.getRouter().dispatch(this.scope, null, options);
            });
        },

        hideHeaderActionItem: function (name) {
            ['actions', 'dropdown', 'buttons'].forEach((t) => {
                (this.menu[t] || []).forEach((item, i) => {
                    item = item || {};

                    if (item.name === name) {
                        item.hidden = true;
                    }
                });
            });

            if (!this.isRendered()) {
                return;
            }

            this.$el.find('.page-header li > .action[data-name="'+name+'"]').parent().addClass('hidden');
            this.$el.find('.page-header a.action[data-name="'+name+'"]').addClass('hidden');

            this.controlMenuDropdownVisibility();
        },

        showHeaderActionItem: function (name) {
            ['actions', 'dropdown', 'buttons'].forEach((t) => {
                (this.menu[t] || []).forEach((item, i) => {
                    item = item || {};

                    if (item.name === name) {
                        item.hidden = false;
                    }
                });
            });

            if (!this.isRendered()) {
                return;
            }

            this.$el.find('.page-header li > .action[data-name="'+name+'"]').parent().removeClass('hidden');
            this.$el.find('.page-header a.action[data-name="'+name+'"]').removeClass('hidden');

            this.controlMenuDropdownVisibility();
        },

        hasMenuVisibleDropdownItems: function () {
            var hasItems = false;

            (this.menu.dropdown || []).forEach((item) => {
                if (!item.hidden) {
                    hasItems = true;
                }
            });

            return hasItems;
        },

        controlMenuDropdownVisibility: function () {
            var $d = this.$el.find('.page-header .dropdown-group');

            if (this.hasMenuVisibleDropdownItems()) {
                $d.removeClass('hidden');
            } else {
                $d.addClass('hidden');
            }
        },
    });
});
