/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2023 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

define('views/fields/foreign-array', ['views/fields/array'], function (Dep) {

    return Dep.extend({

        type: 'foreign',

        setupOptions: function () {
            this.params.options = [];

            if (!this.params.field || !this.params.link) {
                return;
            }

            var scope = this.getMetadata()
                .get(['entityDefs', this.model.name, 'links', this.params.link, 'entity']);

            if (!scope) {
                return;
            }

            const {
                optionsPath, translation
            } = this.getMetadata().get(['entityDefs', scope, 'fields', this.params.field]);

            if (optionsPath) {
                this.params.options = Espo.Utils.clone(this.getMetadata().get(optionsPath)) || [];
            }
            else {
                this.params.options = this.getMetadata()
                    .get(['entityDefs', scope, 'fields', this.params.field, 'options']) || [];
            }

            if (translation) {
                this.params.translation = translation;
            }

            this.params.isSorted = this.getMetadata()
                .get(['entityDefs', scope, 'fields', this.params.field, 'isSorted']) || false;
            this.params.displayAsLabel = this.getMetadata()
                    .get(['entityDefs', scope, 'fields', this.params.field, 'displayAsLabel'])
                || false;
            this.params.displayAsList = this.getMetadata()
                    .get(['entityDefs', scope, 'fields', this.params.field, 'displayAsList'])
                || false;

            this.styleMap = this.getMetadata()
                .get(['entityDefs', scope, 'fields', this.params.field, 'style']) || {};

            this.translatedOptions = {};

            this.params.options.forEach(item => {
                this.translatedOptions[item] = this.getLanguage()
                    .translateOption(item, this.params.field, scope);
            });
        },
    });
});
