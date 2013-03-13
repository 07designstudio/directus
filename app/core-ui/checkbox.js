//  Checkbox core UI component
//  Directus 6.0

//  (c) RANGER
//  Directus may be freely distributed under the GNU license.
//  For all details and documentation:
//  http://www.getdirectus.com

define(['app','backbone'], function(app, Backbone) {

  var Module = {};

  var template = '<label style="margin-top:3px;" class="checkbox"> \
                  <input style="margin-top:1px;" type="checkbox" {{#if selected}}checked{{/if}}/>{{{capitalize name}}} <span class="note">{{comment}}</span></label> \
                  <input type="hidden" name="{{name}}" value="{{#if selected}}1{{else}}0{{/if}}">';

  Module.id = 'checkbox';
  Module.dataTypes = ['TINYINT'];

  Module.Input = Backbone.Layout.extend({
    tagName: 'fieldset',
    template: Handlebars.compile(template),
    events: {
      'change input[type=checkbox]': function(e) {
        var val = (this.$el.find('input[type=checkbox]:checked').val() === undefined) ? 0 : 1;
        this.$el.find('input[type=hidden]').val(val);
      }
    },
    serialize: function() {
      var value = this.options.value;

      // Get default value if there is one
      if (value === undefined && this.options.schema.has('def')) {
        value = this.options.schema.get('def');
      }

      var selected = (parseInt(value,10) === 1) ? true : false;
      return {
        name: this.options.name,
        selected: selected,
        comment: this.options.schema.get('comment')
      };
    }
  });

  Module.list = function(options) {
    var val = (options.value) ? '<input type="checkbox" checked="true" disabled>' : '<input type="checkbox" disabled>';
    //var val = options.value.toString().replace(/<(?:.|\n)*?>/gm, '').substr(0,100);
    return val;//val;
  };


  return Module;
});