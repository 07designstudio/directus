//  Many To One core UI component
//  Directus 6.0

//  (c) RANGER
//  Directus may be freely distributed under the GNU license.
//  For all details and documentation:
//  http://www.getdirectus.com

define(['app', 'backbone'], function(app, Backbone) {

  var Module = {};

  Module.id = 'many_to_one';
  Module.dataTypes = ['INT'];

  Module.variables = [
    {id: 'table_related', ui: 'textinput', char_length: 64},
    {id: 'visible_column', ui: 'textinput', char_length: 64},
    {id: 'use_radio_buttons', ui: 'checkbox', def: '0'}
  ];

  var template = '<label>{{capitalize name}} <span class="note">{{comment}}</span></label> \
                  <style type="text/css"> \
                  label.radiobuttons { \
                    font-weight:normal; \
                    display:inline; \
                    margin-right: 10px; \
                    padding: 4px; \
                  } \
                  </style> \
                  {{#if use_radio_buttons}} \
                  {{#data}}<input style="margin-top:-3px;" type="radio" name="{{../name}}" value="{{id}}" id="radio-{{id}}" {{#if selected}}checked{{/if}}> \
                  <label class="radiobuttons" for="radio-{{id}}">{{name}}</label>{{/data}} \
                  {{else}} \
                  <select name="{{name}}"> \
                  <option value="">Select from below</option> \
                  {{#data}}<option value="{{id}}" {{#if selected}}selected{{/if}}>{{name}}</option>{{/data}} \
                  </select> \
                  {{/if}}';

  Module.Input = Backbone.Layout.extend({

    tagName: 'fieldset',

    template: Handlebars.compile(template),

    serialize: function() {
      var data = this.collection.map(function(model) {
        return {
          id: model.id,
          name: model.get(this.column),
          selected: this.options.value !== undefined && (model.id === this.options.value.id)
        };
      }, this);

      // default data while syncing (to avoid flickr when data is loaded)
      if (this.options.value !== undefined && this.options.value.id && !data.length) {
        data = [{
          id: this.options.value.id,
          name: this.options.value.get(this.column),
          selected: true
        }];
      }

      return {
        name: this.options.name,
        data: data,
        comment: this.options.schema.get('comment'),
        use_radio_buttons: (this.options.settings && this.options.settings.has('use_radio_buttons') && this.options.settings.get('use_radio_buttons') == '1') ? true : false
      };
    },

    initialize: function(options) {
      // @todo display warning on UI & gracefully fail if the next value is undefined
      var relatedTable = options.settings.get('table_related');
      this.column = options.settings.get('visible_column');
      this.collection = app.entries[relatedTable];
      this.collection.fetch();
      //this.collection.on('reset', this.render, this);
      this.collection.on('sync', this.render, this);
    }

  });

  Module.validate = function(value) {

  };

  Module.list = function(options) {
    if (options.value === undefined) return '';
    if (options.value instanceof Backbone.Model) return options.value.get(options.settings.get('visible_column'));
    return options.value;
  };

  return Module;
});