{{#unless onlyContent}}
<li data-id="{{model.id}}" class="list-group-item">
{{/unless}}
	
	<div>
		<span class="text-muted">{{{createdBy}}} {{translate 'updated'}}
		{{#if isUserStream}} {{parentTypeString}} {{{parent}}} {{else}} {{translate 'this'}} {{parentTypeString}}{{/if}}
		<a href="javascript:" data-action="expandDetails"><span class="glyphicon glyphicon-chevron-down"></span></a>
		</span>
	</div>
	
	<div class="hidden details">
		<span>
			{{#each fieldsArr}}
				{{translate field category='fields' scope=../parentType}}: {{{var was ../this}}} {{translate 'to'}} {{{var became ../this}}}
				<br>				
			{{/each}}
		</span>
	</div>
	
	<div>
		<span class="text-muted small">{{{createdAt}}}</span>
	</div>

{{#unless onlyContent}}
</li>
{{/unless}}
