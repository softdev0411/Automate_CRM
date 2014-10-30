{{#unless onlyContent}}
<li data-id="{{model.id}}" class="list-group-item">
{{/unless}}    
    
    <div class="stream-head-container">
        <span class="text-muted"><span class="glyphicon glyphicon-envelope "></span> {{translate 'Email'}} <a href="#Email/view/{{emailId}}">{{emailName}}</a>  
            {{translate 'has been received' category='stream'}}
            {{#if isUserStream}} {{translate 'for' category='stream'}} {{parentTypeString}} {{{parent}}} {{/if}} 
        </span>        
    </div>


    {{#if post}}
    <div class="stream-post-container">
        <span class="cell cell-post">{{{post}}}</span>
    </div>
    {{/if}}
    
    {{#if attachments}}
    <div class="stream-attachments-container">        
        <span class="cell cell-attachments">{{{attachments}}}</span>
    </div>
    {{/if}}
    
    <div class="stream-date-container">
        <span class="text-muted small">{{{createdAt}}}</span>
    </div>
    
{{#unless onlyContent}}
</li>
{{/unless}}
