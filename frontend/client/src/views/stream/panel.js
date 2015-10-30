/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2015 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
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
 ************************************************************************/

Espo.define('views/stream/panel', ['views/record/panels/relationship', 'lib!Textcomplete'], function (Dep, Textcomplete) {

    return Dep.extend({

        template: 'stream/panel',

        postingMode: false,

        postDisabled: false,

        events: _.extend({
            'focus textarea.note': function (e) {
                this.enablePostingMode();
            },
            'click button.post': function () {
                this.post();
            },
            'keypress textarea.note': function (e) {
                if ((e.keyCode == 10 || e.keyCode == 13) && e.ctrlKey) {
                    this.post();
                } else if (e.keyCode == 9) {
                    $text = $(e.currentTarget)
                    if ($text.val() == '') {
                        this.disablePostingMode();
                    }
                }
            },
            'input textarea.note': function (e) {
                var $text = $(e.currentTarget);
                var numberOfLines = e.currentTarget.value.split("\n").length;
                var numberOfRows = $text.prop('rows');

                if (numberOfRows < numberOfLines) {
                    $text.prop('rows', numberOfLines);
                } else if (numberOfRows > numberOfLines) {
                    $text.prop('rows', numberOfLines);
                }
            },
        }, Dep.prototype.events),

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.postDisabled = this.postDisabled;
            data.placeholderText = this.placeholderText;
            return data;
        },

        enablePostingMode: function () {
            this.$el.find('.buttons-panel').removeClass('hide');

            if (!this.postingMode) {
                $('body').on('click.stream-panel', function (e) {
                    if (!$.contains(this.$postContainer.get(0), e.target)) {
                        if (this.$textarea.val() == '') {
                            var attachmentsIds = this.seed.get('attachmentsIds');
                            if (!attachmentsIds.length) {
                                this.disablePostingMode();
                            }
                        }
                    }
                }.bind(this));
            }

            this.postingMode = true;
        },

        disablePostingMode: function () {
            this.postingMode = false;

            this.$textarea.val('');
            if (this.hasView('attachments')) {
                this.getView('attachments').empty();
            }
            this.$el.find('.buttons-panel').addClass('hide');

            $('body').off('click.stream-panel');
        },

        setup: function () {
            this.title = this.translate('Stream');

            this.scope = this.model.name;

            this.filter = this.getStoredFilter();

            this.placeholderText = this.translate('writeYourCommentHere', 'messages');

            this.wait(true);
            this.getModelFactory().create('Note', function (model) {
                this.seed = model;
                this.createCollection(function () {
                    this.wait(false);
                }, this);
            }, this);
        },

        createCollection: function (callback, context) {
            this.getCollectionFactory().create('Note', function (collection) {
                this.collection = collection;
                collection.url = this.model.name + '/' + this.model.id + '/stream';
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;
                this.setFilter(this.filter);

                callback.call(context);
            }, this);
        },

        afterRender: function () {
            this.$textarea = this.$el.find('textarea.note');
            this.$attachments = this.$el.find('div.attachments');
            this.$postContainer = this.$el.find('.post-container');

            var collection = this.collection;

            this.listenToOnce(collection, 'sync', function () {
                this.createView('list', 'views/stream/record/list', {
                    el: this.options.el + ' > .list-container',
                    collection: collection,
                    model: this.model
                }, function (view) {
                    view.render();
                });

                setTimeout(function () {
                    this.listenTo(this.model, 'sync', function () {
                        collection.fetchNew();
                    }, this);
                }.bind(this), 500);

            }, this);
            if (!this.defs.hidden) {
                collection.fetch();
            }

            var assignmentPermission = this.getAcl().get('assignmentPermission');

            var buildUserListUrl = function (term) {
                var url = 'User?orderBy=name&limit=7&q=' + term + '&' + $.param({'primaryFilter': 'active'});
                if (assignmentPermission == 'team') {
                    url += '&' + $.param({'boolFilterList': ['onlyMyTeam']})
                }
                return url;
            }.bind(this);

            if (assignmentPermission !== 'no') {
                this.$textarea.textcomplete([{
                    match: /(^|\s)@(\w*)$/,
                    index: 2,
                    search: function (term, callback) {
                        if (term.length == 0) {
                            callback([]);
                            return;
                        }
                        $.ajax({
                            url: buildUserListUrl(term),
                        }).done(function (data) {
                            callback(data.list)
                        });
                    },
                    template: function (mention) {
                        return mention.name + ' <span class="text-muted">@' + mention.userName + '</span>';
                    },
                    replace: function (o) {
                        return '$1@' + o.userName + '';
                    }
                }]);

                this.once('remove', function () {
                    if (this.$textarea.size()) {
                        this.$textarea.textcomplete('destroy');
                    }
                }, this);
            }

            $a = this.$el.find('.buttons-panel a.stream-post-info');

            $a.popover({
                placement: 'bottom',
                container: 'body',
                content: this.translate('streamPostInfo', 'messages').replace(/(\r\n|\n|\r)/gm, '<br>'),
                trigger: 'click',
                html: true
            }).on('shown.bs.popover', function () {
                $('body').one('click', function () {
                    $a.popover('hide');
                });
            });

            this.createView('attachments', 'Stream.Fields.AttachmentMultiple', {
                model: this.seed,
                mode: 'edit',
                el: this.options.el + ' div.attachments-container',
                defs: {
                    name: 'attachments',
                },
            }, function (view) {
                view.render();
            });
        },

        afterPost: function () {
            this.$el.find('textarea.note').prop('rows', 1);
        },

        post: function () {
            var message = this.$textarea.val();

            this.$textarea.prop('disabled', true);

            this.getModelFactory().create('Note', function (model) {
                if (message == '' && this.seed.get('attachmentsIds').length == 0) {
                    this.notify('Post cannot be empty', 'error');
                    this.$textarea.prop('disabled', false);
                    return;
                }

                model.once('sync', function () {
                    this.notify('Posted', 'success');
                    this.collection.fetchNew();

                    this.$textarea.prop('disabled', false);
                    this.disablePostingMode();
                    this.afterPost();
                }, this);

                model.set('post', message);
                model.set('attachmentsIds', _.clone(this.seed.get('attachmentsIds')));
                model.set('type', 'Post');

                this.prepareNoteForPost(model);


                this.notify('Posting...');
                model.save(null, {
                    error: function () {
                        this.$textarea.prop('disabled', false);
                    }.bind(this)
                });
            }.bind(this));
        },

        prepareNoteForPost: function (model) {
            model.set('parentId', this.model.id);
            model.set('parentType', this.model.name);
        },

        getButtonList: function () {
            return [];
        },

        filterList: ['all', 'posts', 'updates'],

        getActionList: function () {
            var list = [];
            this.filterList.forEach(function (item) {
                var selected = false;
                if (item == 'all') {
                    selected = !this.filter;
                } else {
                    selected = item === this.filter;
                }
                list.push({
                    action: 'selectFilter',
                    html: '<span class="glyphicon glyphicon-ok pull-right' + (!selected ? ' hidden' : '') + '"></span>' + this.translate(item, 'filters', 'Note'),
                    data: {
                        name: item
                    }
                });
            }, this);
            return list;
        },

        getStoredFilter: function () {
            return this.getStorage().get('state', 'streamPanelFilter' + this.scope) || null;
        },

        storeFilter: function (filter) {
            if (filter) {
                this.getStorage().set('state', 'streamPanelFilter' + this.scope, filter);
            } else {
                this.getStorage().clear('state', 'streamPanelFilter' + this.scope);
            }
        },

        setFilter: function (filter) {
            this.collection.data.filter = null;
            if (filter) {
                this.collection.data.filter = filter;
            }
        },

        actionSelectFilter: function (data) {
            var filter = data.name;
            var filterInternal = filter;
            if (filter == 'all') {
                filterInternal = false;
            }
            this.storeFilter(filterInternal);
            this.setFilter(filterInternal);

            this.filterList.forEach(function (item) {
                var $el = this.$el.closest('.panel').find('[data-name="'+item+'"] span');
                if (item === filter) {
                    $el.removeClass('hidden');
                } else {
                    $el.addClass('hidden');
                }
            }, this);
            this.collection.reset();
            this.collection.fetch();
        },

        actionRefresh: function () {
            if (this.hasView('list')) {
                this.getView('list').showNewRecords();
            }
        },

    });
});

