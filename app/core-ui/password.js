//  Password Core UI component
//  Directus 6.0

//  (c) RANGER
//  Directus may be freely distributed under the GNU license.
//  For all details and documentation:
//  http://www.getdirectus.com

define(['app', 'backbone'], function(app, Backbone) {

  var Module = {};

  Module.id = 'password';
  Module.dataTypes = ['VARCHAR'];

  Module.variables = [
    {id: 'require_confirmation', ui: 'checkbox', def: '1'}
  ];

  var template = '<label>Change Password <span class="note">{{comment}}</span></label> \
                 <input type="password" name="{{name}}" class="medium password-primary"/> \
                 <button class="btn btn-small btn-primary margin-left password-generate" type="button">Generate New</button> \
                 <button class="btn btn-small btn-primary margin-left password-toggle" type="button">Reveal Password</button> \
                 <span class="password-text"></span> \
                 {{#if require_confirmation}} \
                 <label style="margin-top:12px">Confirm Password</label> \
                 <input type="password" value="{{value}}" class="medium password-confirm"/> \
                 {{/if}}';

  Module.Input = Backbone.Layout.extend({

    tagName: 'fieldset',

    template: Handlebars.compile(template),

    events: {
      'click .password-generate' : function(e) {

        var length = 10,
            charset = "abcdefghijklnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789",
            pass = "";

        for (var i = 0, n = charset.length; i < length; ++i) {
          pass += charset.charAt(Math.floor(Math.random() * n));
        }
        this.$el.find('input.password-primary').val(pass);
        this.$el.find('input.password-confirm').val(pass);
        e.preventDefault();
      },
      'click .password-toggle' : function(e) {
        if($(e.target).html() == 'Mask Password'){
          this.$el.find('input.password-primary').get(0).type = 'password';
          this.$el.find('input.password-confirm').get(0).type = 'password';
          $(e.target).html('Reveal Password');
        } else {
          this.$el.find('input.password-primary').get(0).type = 'text';
          this.$el.find('input.password-confirm').get(0).type = 'text';
          $(e.target).html('Mask Password');
        }
      }
    },

    serialize: function() {
      return {
        name: this.options.name,
        comment: this.options.schema.get('comment'),
        require_confirmation: (this.options.settings && this.options.settings.has('require_confirmation') && this.options.settings.get('require_confirmation') == '0') ? false : true
      };
    }

  });

  Module.validate = function(value) {
    // We need a way to validate the value against the CONFIRM value... but we don't have access to that CONFIRM value here
  };

  Module.list = function(options) {
    return (options.value) ? options.value.toString().replace(/<(?:.|\n)*?>/gm, '').substr(0,100) : '';
  };

  return Module;

});