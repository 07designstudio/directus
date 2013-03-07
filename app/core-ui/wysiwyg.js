//  Text Input Core UI component
//  Directus 6.0

//  (c) RANGER
//  Directus may be freely distributed under the GNU license.
//  For all details and documentation:
//  http://www.getdirectus.com

define(['app', 'backbone'], function(app, Backbone) {

  var Module = {};

  Module.id = 'wysiwyg';
  Module.dataTypes = ['VARCHAR', 'TEXT'];

  Module.variables = [
    {id: 'readonly', ui: 'checkbox'},
    {id: 'height', ui: 'numeric'},
    {id: 'bold', ui: 'checkbox'},
    {id: 'italic', ui: 'checkbox'},
    {id: 'underline', ui: 'checkbox'},
    {id: 'strikethrough', ui: 'checkbox'},
    {id: 'rule', ui: 'checkbox'},
    {id: 'createlink', ui: 'checkbox'},
    {id: 'insertimage', ui: 'checkbox'}
  ];

  var template =  '<label>{{capitalize name}} <span class="note">{{note}}</span></label>'+
                  '<div class="btn-group btn-group-attached btn-group-action active">'+
                    '{{#if bold}}<button type="button" class="btn btn-small btn-silver" data-tag="bold" rel="tooltip" data-placement="bottom" title="Bold"><b>B</b></button>{{/if}}'+
                    '{{#if italic}}<button type="button" class="btn btn-small btn-silver" data-tag="italic" rel="tooltip" data-placement="bottom" title="Italic"><i>I</i></button>{{/if}}'+
                    '{{#if underline}}<button type="button" class="btn btn-small btn-silver" data-tag="underline" rel="tooltip" data-placement="bottom" title="Underline"><u>U</u></button>{{/if}}'+
                    '{{#if strikethrough}}<button type="button" class="btn btn-small btn-silver" data-tag="strikethrough" rel="tooltip" data-placement="bottom" title="Strikethrough"><s>S</s></button>{{/if}}'+
                  '</div>'+
                  '<div class="btn-group btn-group-attached btn-group-action active">'+
                    '{{#if rule}}<button type="button" class="btn btn-small btn-silver" data-tag="inserthorizontalrule" rel="tooltip" data-placement="bottom" title="HR">HR</button>{{/if}}'+
                    '{{#if createlink}}<button type="button" class="btn btn-small btn-silver" data-tag="createlink" rel="tooltip" data-placement="bottom" title="Link">Link</button>'+
                    '<button type="button" class="btn btn-small btn-silver" data-tag="unlink" rel="tooltip" data-placement="bottom" title="Unlink">Unlink</button>{{/if}}'+
                    '{{#if insertimage}}<button type="button" class="btn btn-small btn-silver" data-tag="insertimage" rel="tooltip" data-placement="bottom" title="Image">Image</button>{{/if}}'+
                  '</div>'+
                  '<div class="force-editable" style="display:block; height:{{height}}px;" contenteditable="true" id="{{name}}">{{value}}</div>'+
                  '<input type="hidden" name="{{name}}" value="{{markupValue}}">';

//<span class="glyphicon-eye-close"></span>

  Module.Input = Backbone.Layout.extend({

    tagName: 'fieldset',

    template: Handlebars.compile(template),

    events: {
      'focus input': function() { this.$el.find('.label').show(); },
      'keyup input': 'updateMaxLength',
      'blur input': function() { this.$el.find('.label').hide(); },
      'click button' : function(e) {

        var tag = $(e.target).attr('data-tag');
        var value = null;

        if(tag == 'createlink' || tag == 'insertimage'){
          this.saveSelection();
          value = prompt("Please enter your link", "http://example.com");
          this.restoreSelection();

          if(value === ''){
            return false;
          }
        }

        document.execCommand(tag,false,value);
      },
      'blur div.force-editable' : function(e) {
        var innerHtml = $(e.target).html();
        //console.log('before',innerHtml);
        //innerHtml = String(innerHtml).replace(/"/g, '&quot;');
        this.$el.find('input').val(innerHtml);
        console.log('after',innerHtml);
      }
    },

    updateMaxLength: function(e) {
      var length = this.options.schema.get('char_length') - e.target.value.length;
      this.$el.find('.label').html(length);
    },

    afterRender: function() {
      if (this.options.settings.get("readonly") === "on") this.$("input").prop("readonly",true);
    },

    serialize: function() {
      var length = this.options.schema.get('char_length');
      var value = this.options.value || '';
      console.log('NEW',value);
      return {
        height: (this.options.settings && this.options.settings.has('height')) ? this.options.settings.get('height') : '100',
        bold: (this.options.settings && this.options.settings.has('bold')) ? this.options.settings.get('bold') : false,
        italic: (this.options.settings && this.options.settings.has('italic')) ? this.options.settings.get('italic') : false,
        underline: (this.options.settings && this.options.settings.has('underline')) ? this.options.settings.get('underline') : false,
        strikethrough: (this.options.settings && this.options.settings.has('strikethrough')) ? this.options.settings.get('strikethrough') : false,
        rule: (this.options.settings && this.options.settings.has('rule')) ? this.options.settings.get('rule') : false,
        createlink: (this.options.settings && this.options.settings.has('createlink')) ? this.options.settings.get('createlink') : false,
        insertimage: (this.options.settings && this.options.settings.has('insertimage')) ? this.options.settings.get('insertimage') : false,
        markupValue: String(value).replace(/"/g, '&quot;'),
        value: new Handlebars.SafeString(value),
        name: this.options.name,
        maxLength: length,
        characters: length - value.length,
        note: this.options.schema.get('comment')
      };
    },

    saveSelection: function() {
        if (window.getSelection) {
            sel = window.getSelection();
            if (sel.getRangeAt && sel.rangeCount) {
                var ranges = [];
                for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                    ranges.push(sel.getRangeAt(i));
                }
                return ranges;
            }
        } else if (document.selection && document.selection.createRange) {
            return document.selection.createRange();
        }
        return null;
    },

    restoreSelection: function(savedSel) {
        if (savedSel) {
            if (window.getSelection) {
                sel = window.getSelection();
                sel.removeAllRanges();
                for (var i = 0, len = savedSel.length; i < len; ++i) {
                    sel.addRange(savedSel[i]);
                }
            } else if (document.selection && savedSel.select) {
                savedSel.select();
            }
        }
    },

    initialize: function() {
      //
    }

  });

  Module.validate = function(value) {
    return true;
  };

  Module.list = function(options) {
    return (options.value) ? options.value.toString().replace(/<(?:.|\n)*?>/gm, '').substr(0,100) : '';
  };

  return Module;

});