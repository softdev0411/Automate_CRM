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
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/
if (process.argv.length < 2) {
    throw new Error('No dir argument passed');
}

var path = require('path');
var fs = require('fs');

var language = process.argv[2];

var espoPath = path.dirname(fs.realpathSync(__filename)) + '';

function PO (espoPath, language) {
    this.moduleList = ['Crm'];
    this.baseLanguage = 'en_US';
    this.language = language || this.baseLanguage;

    this.currentPath = path.dirname(fs.realpathSync(__filename)) + '/';

    this.outputFileName = 'espocrm-' + this.language + '.po';

    this.path = espoPath;
    if (this.path.substr(-1) != '/') {
        this.path += '/';
    }

    var dirs = [
        this.path + 'application/Espo/Resources/i18n/',
        this.path + 'install/core/i18n/'
    ];
    this.moduleList.forEach(function (moduleName) {
        dirs.push(this.path + 'application/Espo/Modules/' + moduleName + '/Resources/i18n/');
    }, this);

    this.dirs = dirs;

    this.poContentHeader = 'msgid ""\n' +
        'msgstr ""\n' +
        '"Project-Id-Version: \\n"\n' +
        '"POT-Creation-Date: \\n"\n' +
        '"PO-Revision-Date: \\n"\n' +
        '"Last-Translator: \\n"\n' +
        '"Language-Team: EspoCRM <infobox@espocrm.com>\\n"\n' +
        '"MIME-Version: 1.0\\n"\n' +
        '"Content-Type: text/plain; charset=UTF-8\\n"\n' +
        '"Content-Transfer-Encoding: 8bit\\n"\n' +
        '"Language: ' + this.language + '\\n"\n\n';
};

PO.prototype.run = function () {
    var dirs = this.dirs;
    var messageList = [];
    var langMessageList = [];
    var self = this;
    var poContents = this.poContentHeader;

    dirs.forEach(function (path) {

        var dirPath = this.getDirPath(path, self.baseLanguage);

        var list = fs.readdirSync(dirPath);
        list.forEach(function (fileName) {

            var filePath = this.getDirPath(path, self.baseLanguage) + fileName;
            messageList = this.getMessageList(filePath, messageList);

            if (self.language != self.baseLanguage) {
                var langFilePath = this.getDirPath(path, self.language) + fileName;
                langMessageList = this.getMessageList(langFilePath, langMessageList);
            }

        }, this);

    }, this);


    if (self.language == self.baseLanguage) {
        langMessageList = messageList;
    }

    for (var index in messageList) {
        poContents += 'msgid "' + messageList[index] + '"\n';

        var langMessage = langMessageList[index] || "";
        poContents += 'msgstr "' + langMessage + '"\n\n';
    }

    var resFilePath = this.currentPath + 'build/' + this.outputFileName;

    if (fs.existsSync(resFilePath)) {
        fs.unlinkSync(resFilePath);
    }

    fs.writeFileSync(resFilePath, poContents);
};

PO.prototype.getMessageList = function (filePath, currentMessageList) {
    if (!fs.existsSync(filePath)) {
        return currentMessageList;
    }

    var data = fs.readFileSync(filePath, 'utf8');
    data = JSON.parse(data);

    currentMessageList = this.convertToSigleObject(data, '', currentMessageList);

    return currentMessageList;
}

PO.prototype.getDirPath = function (path, language) {
    var dirPath = path + language + '/';
    return dirPath;
}

PO.prototype.convertToSigleObject = function (dataObject, prefix, currentMessageList) {

    prefix = prefix || '';

    for (var index in dataObject) {
        if (dataObject[index] === null || dataObject[index] === "") {
            continue;
        }

        if (Array.isArray(dataObject[index])) {
            dataObject[index] = '"' + dataObject[index].join('", "') + '"';
        }

        if (typeof dataObject[index] === 'object') {
            var nextPrefix = prefix + index + ".";
            currentMessageList = this.convertToSigleObject(dataObject[index], nextPrefix, currentMessageList);
        } else {
            if (!this.objectIndexOf(dataObject[index], currentMessageList)) {
                var key = prefix + index;
                key = this.checkFixDuplicateKey(key, currentMessageList);
                var savedString = this.fixString(dataObject[index]);

                currentMessageList[key] = savedString;
            }
        }
    }

    return currentMessageList;
}

PO.prototype.objectIndexOf = function (value, data) {
    for (var index in data) {
        if (data[index] !== null && data[index] === value) {
            return true;
        }
    }
    return false;
}

PO.prototype.checkFixDuplicateKey = function (key, data) {
    if (this.objectIndexOfKey(key, data)) {
        key = key + "+";
        key = this.checkFixDuplicateKey(key, data);
    }
    return key;
}

PO.prototype.objectIndexOfKey = function (key, data) {
    for(var index in data) {
        if (index !== null && index === key) {
            return true;
        }
    }
    return false;
}

PO.prototype.replaceAll = function (string, find, replace) {
    escapedRegExp = find.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, "\\$1");
    return string.replace(new RegExp(escapedRegExp, 'g'), replace);
}

PO.prototype.fixString = function (savedString) {
    savedString = this.replaceAll(savedString, '"', '\\"');
    savedString = this.replaceAll(savedString, "\n", '\\n');
    savedString = this.replaceAll(savedString, "\t", '\\t');
    return savedString;
}

var po = new PO(espoPath, language);

po.run();

