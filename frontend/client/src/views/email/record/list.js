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

Espo.define('views/email/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        rowActionsView: 'views/email/record/row-actions/default',

        massActionList: ['remove', 'massUpdate'],

        buttonList: [
            {
                name: 'markAllAsRead',
                label: 'Mark all as read',
                style: 'default'
            }
        ],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.massActionList.push('markAsRead');
            this.massActionList.push('markAsNotRead');
            this.massActionList.push('markAsImportant');
            this.massActionList.push('markAsNotImportant');
        },

        massActionMarkAsRead: function () {
            var ids = [];
            for (var i in this.checkedList) {
                ids.push(this.checkedList[i]);
            }
            $.ajax({
                url: 'Email/action/markAsRead',
                type: 'POST',
                data: JSON.stringify({
                    ids: ids
                })
            });
            ids.forEach(function (id) {
                var model = this.collection.get(id);
                if (model) {
                    model.set('isRead', true);
                }
            }, this);
        },

        massActionMarkAsNotRead: function () {
            var ids = [];
            for (var i in this.checkedList) {
                ids.push(this.checkedList[i]);
            }
            $.ajax({
                url: 'Email/action/markAsNotRead',
                type: 'POST',
                data: JSON.stringify({
                    ids: ids
                })
            });
            ids.forEach(function (id) {
                var model = this.collection.get(id);
                if (model) {
                    model.set('isRead', false);
                }
            }, this);
        },

        massActionMarkAsImportant: function () {
            var ids = [];
            for (var i in this.checkedList) {
                ids.push(this.checkedList[i]);
            }
            $.ajax({
                url: 'Email/action/markAsImportant',
                type: 'POST',
                data: JSON.stringify({
                    ids: ids
                })
            });
            ids.forEach(function (id) {
                var model = this.collection.get(id);
                if (model) {
                    model.set('isImportant', true);
                }
            }, this);
        },

        massActionMarkAsNotImportant: function () {
            var ids = [];
            for (var i in this.checkedList) {
                ids.push(this.checkedList[i]);
            }
            $.ajax({
                url: 'Email/action/markAsNotImportant',
                type: 'POST',
                data: JSON.stringify({
                    ids: ids
                })
            });
            ids.forEach(function (id) {
                var model = this.collection.get(id);
                if (model) {
                    model.set('isImportant', false);
                }
            }, this);
        },

        actionMarkAsImportant: function (data) {
            data = data || {};
            var id = data.id;
            $.ajax({
                url: 'Email/action/markAsImportant',
                type: 'POST',
                data: JSON.stringify({
                    id: id
                })
            });
            var model = this.collection.get(id);
            if (model) {
                model.set('isImportant', true);
            }
        },

        actionMarkAsNotImportant: function (data) {
            data = data || {};
            var id = data.id;
            $.ajax({
                url: 'Email/action/markAsNotImportant',
                type: 'POST',
                data: JSON.stringify({
                    id: id
                })
            });
            var model = this.collection.get(id);
            if (model) {
                model.set('isImportant', false);
            }
        },

        actionMarkAllAsRead: function () {
            $.ajax({
                url: 'Email/action/markAllAsRead',
                type: 'POST'
            });
            this.collection.forEach(function (model) {
                model.set('isRead', true);
            }, this);
        }

    });
});

