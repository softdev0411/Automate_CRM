{{#if idValue}}{{#if iconHtml}}{{{iconHtml}}}{{/if}}<a href="#{{foreignScope}}/view/{{idValue}}">{{nameValue}}</a>{{else}}
    {{#if valueIsSet}}<span class="none-value">{{translate 'None'}}</span>{{else}}
    <span class="loading-value">...</span>{{/if}}
{{/if}}
