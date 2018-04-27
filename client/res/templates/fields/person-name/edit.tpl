<div class="row">
    <div class="col-sm-3 col-xs-3">
        <select name="salutation{{ucName}}" class="form-control">
            {{options salutationOptions salutationValue field='salutationName' scope=scope}}
        </select>
    </div>
    <div class="col-sm-4 col-xs-4">
        <input type="text" class="form-control" name="first{{ucName}}" value="{{firstValue}}" placeholder="{{translate 'First Name'}}"{{#if firstMaxLength}} maxlength="{{firstMaxLength}}"{{/if}} autocomplete="off">
    </div>
    <div class="col-sm-5 col-xs-5">
        <input type="text" class="form-control" name="last{{ucName}}" value="{{lastValue}}" placeholder="{{translate 'Last Name'}}"{{#if lastMaxLength}} maxlength="{{lastMaxLength}}"{{/if}} autocomplete="off">
    </div>
</div>
