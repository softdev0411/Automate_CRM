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

Espo.define('views/user/record/detail-side', 'views/record/detail-side', function (Dep) {

    return Dep.extend({

        panelList: [
            {
                name: 'default',
                label: false,
                view: 'Record.Panels.Side',
                options: {
                    fieldList: ['avatar'],
                    mode: 'detail',
                }
            }
        ],

        setupPanels: function () {
            Dep.prototype.setupPanels.call(this);

            var showActivities = this.getAcl().checkUserPermission(this.model);
            if (!showActivities) {
                if (this.getAcl().get('userPermission') === 'team') {
                    if (!this.model.has('teamsIds')) {
                        this.listenToOnce(this.model, 'sync', function () {
                            if (this.getAcl().checkUserPermission(this.model)) {
                                this.getParentView().showPanel('activities');
                                this.getParentView().showPanel('history');
                                this.getView('activities').actionRefresh();
                                this.getView('history').actionRefresh();
                            }
                        }, this);
                    }
                }
            }

            this.panelList.push({
                "name":"activities",
                "label":"Activities",
                "view":"crm:views/record/panels/activities",
                "hidden": !showActivities
            });
            this.panelList.push({
                "name":"history",
                "label":"History",
                "view":"crm:views/record/panels/history",
                "hidden": !showActivities
            });
        }

    });

});

