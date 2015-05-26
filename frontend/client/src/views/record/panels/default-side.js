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

Espo.define('Views.Record.Panels.DefaultSide', 'Views.Record.Panels.Side', function (Dep) {

    return Dep.extend({

        template: 'record.panels.default-side',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.createField('modifiedBy', true);
            this.createField('modifiedAt', true);
            this.createField('createdBy', true);
            this.createField('createdAt', true);
            if (this.getMetadata().get('scopes.' + this.model.name + '.stream')) {
                this.createField('followers', true, 'Fields.Followers');
            }
        },
    });
});

