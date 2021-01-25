/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2021 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

/**
 * Builds upgrade packages.
 * From a specified version to the current version or all packages needed for a release.

 * Examples:
 * * `node diff 5.9.0` - builds  an upgrade from 5.9.0 to the current version;
 * * `node diff --all` - builds all upgrades needed for a release.
 */

const Diff = require('./js/diff');
const path = require('path');
const fs = require('fs');

var versionFrom = process.argv[2];

var acceptedVersionName = versionFrom;
var isDev = false;
var isAll = false;
var withVendor = true;
var forceScripts = false;

if (process.argv.length > 1) {
    for (var i in process.argv) {
        if (process.argv[i] === '--dev') {
            isDev = true;
            withVendor = false;
        }
        if (process.argv[i] === '--all') {
            isAll = true;
        }
        if (process.argv[i] === '--no-vendor') {
            withVendor = false;
        }
        if (process.argv[i] === '--scripts') {
            forceScripts = true;
        }
        if (~process.argv[i].indexOf('--acceptedVersion=')) {
            acceptedVersionName = process.argv[i].substr(('--acceptedVersion=').length);
        }
    }
}

var espoPath = path.dirname(fs.realpathSync(__filename));

if (isAll) {
    var diff = new Diff(espoPath, {
        isDev: isDev,
        withVendor: withVendor,
        forceScripts: forceScripts,
    });
    var versionFromList = diff.getPreviousVersionList();
    diff.buildMultipleUpgradePackages(versionFromList);
} else {
    if (!versionFrom) {
        throw new Error("No 'version' specified.");
    }

    var diff = new Diff(espoPath, {
        acceptedVersionName: acceptedVersionName,
        isDev: isDev,
        withVendor: withVendor,
        forceScripts: forceScripts,
    });
    diff.buildUpgradePackage(versionFrom);
}
