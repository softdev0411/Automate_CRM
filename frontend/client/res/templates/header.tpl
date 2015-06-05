<div class="row">
    <div class="col-lg-7 col-sm-7">
        <h3>{{{header}}}</h3>
    </div>
    <div class="col-lg-5 col-sm-5">
        <div class="header-buttons btn-group pull-right">
            {{#each items.buttons}}
                {{#if link}}
                    <a href="{{link}}" class="btn btn-{{#if style}}{{style}}{{else}}default{{/if}}{{#if action}} action{{/if}}" {{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>{{translate label scope=../../scope}}</a>
                {{else}}
                    <button type="button" class="btn btn-{{#if style}}{{style}}{{else}}default{{/if}} action"{{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>
                        {{#if icon}}<span class="{{icon}}"></span>{{/if}}
                        {{translate label scope=../../scope}}
                    </button>
                {{/if}}
            {{/each}}

            {{#if items.actions}}
                <div class="btn-group" role="group">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                    {{translate 'Actions'}} <span class="caret"></span>
                </button>
                <ul class="dropdown-menu pull-right">
                    {{#each items.actions}}
                    <li><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action" {{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>{{translate label scope=../../scope}}</a></li>
                    {{/each}}
                </ul>
                </div>
            {{/if}}

            {{#if items.dropdown}}
                <div class="btn-group" role="group">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu pull-right">
                    {{#each items.dropdown}}
                    <li><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action" {{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>{{translate label scope=../../scope}}</a></li>
                    {{/each}}
                </ul>
                </div>
            {{/if}}
        </div>
    </div>
</div>

