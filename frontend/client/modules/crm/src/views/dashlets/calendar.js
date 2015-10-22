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

Espo.define('crm:views/dashlets/calendar', 'views/dashlets/abstract/base', function (Dep) {

    return Dep.extend({

        name: 'Calendar',

        _template: '<div class="calendar-container">{{{calendar}}}</div>',

        defaultOptions: {
            mode: 'basicWeek',
            isDoubleHeight: false,
            enabledScopeList: ['Meeting', 'Call', 'Task']
        },

        setup: function () {
            this.optionsFields['mode'] = {
                type: 'enum',
                options: ['basicWeek', 'agendaWeek', 'month'],
            };
            this.optionsFields['enabledScopeList'] = {
                type: 'multiEnum',
                options: ['Meeting', 'Call', 'Task'],
                translation: 'Global.scopeNamesPlural',
                required: true
            };
            this.optionsFields['isDoubleHeight'] = {
                type: 'bool',
            };
        },

        afterRender: function () {
            var height = 296;
            if (this.getOption('isDoubleHeight')) {
                height = 643;
            }

            this.createView('calendar', 'crm:views/calendar/calendar', {
                mode: this.getOption('mode'),
                el: this.$el.selector + ' > .calendar-container',
                header: false,
                height: height,
                enabledScopeList: this.getOption('enabledScopeList')
            }, function (view) {
                view.render();
            });
        }

    });
});


