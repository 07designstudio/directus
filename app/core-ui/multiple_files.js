//  Relational core UI component
//  Directus 6.0

//  (c) RANGER
//  Directus may be freely distributed under the GNU license.
//  For all details and documentation:
//  http://www.getdirectus.com
/*jshint multistr: true */

define(['app', 'backbone', 'sortable', 'core/UIView', 'core/overlays/overlays'], function(app, Backbone, Sortable, UIView, Overlays) {

  "use strict";

  var Module = {};

  Module.id = 'multiple_files';
  Module.dataTypes = ['MANYTOMANY'];

  Module.variables = [
    {id: 'add_button', ui: 'checkbox', def: '1'},
    {id: 'choose_button', ui: 'checkbox', def: '1'},
    {id: 'remove_button', ui: 'checkbox', def: '1'},
  ];

  Module.Input = UIView.extend({

    tagName: 'div',

    attributes: {
      'class': 'field'
    },

    events: {
      'click button[data-action=add]': 'addItem',
      'click button[data-action=insert]': 'insertItem',
      'click .remove-slideshow-item': 'removeItem',
      'click .media-slideshow-item > img': function(e) {
        if (!this.canEdit) {
          return;
        }
        var cid = $(e.target).attr('data-file-cid');
        var model = this.relatedCollection.get(cid, true);
        this.editModel(model);
      }
    },

    template: Handlebars.compile(
      '<style type="text/css"> \
        .ui-file-container:after { \
          clear: both; \
          content: ""; \
          display: block; \
          width: 100%; \
        } \
        .media-slideshow-item { \
          cursor: {{#if sortable}}move{{else}}pointer{{/if}}; \
          width: 160px; \
          float: left; \
          height: 160px; \
          position: relative; \
        } \
        .media-slideshow-item img { \
          width: 100%; \
          height: 100%; \
        } \
        .remove-hover-state .show-circle:hover .white-circle { \
          opacity: 0.0; \
        } \
        div.single-image-thumbnail.empty { \
          float: left; \
          background-color: #ffffff; \
          color: #ededed; \
          text-align: center; \
          cursor: pointer; \
          width: 156px; \
          height: 156px; \
          background-color: #ffffff; \
          border: 2px dashed #bbbbbb; \
          font-size: 12px; \
          font-weight: 600; \
          line-height: 14px; \
          color: #bbbbbb; \
        } \
        div.single-image-thumbnail.empty span { \
          margin-top: 0; \
          display: inline-block; \
          line-height: 18px; \
        } \
        div.single-image-thumbnail.empty span div.icon { \
          display: block; \
          font-size: 100px; \
          line-height: 90px; \
        } \
        div.single-image-thumbnail.empty.dragover, \
        div.single-image-thumbnail.empty:hover { \
          background-color: #BBBBBB; \
          color: #ffffff; \
          cursor: pointer; \
        } \
      </style> \
      <div class="ui-file-container">{{#rows}}<span class="media-slideshow-item show-circle margin-right-small margin-bottom-small"><img data-file-cid="{{cid}}" data-file-id="{{id}}" src={{url}}>{{#if ../showRemoveButton}}<div class="remove-slideshow-item large-circle white-circle"><span class="icon icon-cross"></span></div>{{/if}}</span>{{/rows}}<div class="swap-method single-image-thumbnail empty ui-thumbnail-dropzone"><span><div class="icon icon-picture"></div>Drag and drop<br>file here</span></div></div> \
      <div class="related-table"></div> \
      <div class="btn-row">{{#if showAddButton}}<button class="btn btn-primary margin-right-small" data-action="add" type="button">Add New Files</button>{{/if}} \
      {{#if showChooseButton}}<button class="btn btn-primary" data-action="insert" type="button">Choose Existing Files</button>{{/if}}</div>'),

    addItem: function() {
      this.addModel(new this.relatedCollection.nestedCollection.model({}, {collection: this.relatedCollection.nestedCollection, parse: true}));
    },

    removeItem: function(e) {
      console.log("gone");
      var target_cid = $(e.target).closest('.media-slideshow-item').find('img').attr('data-file-cid');
      var model = this.relatedCollection.get(target_cid);

      if (model.isNew()) return this.relatedCollection.remove(model);

      var name = {};
      name[app.statusMapping.status_name] = app.statusMapping.deleted_num;
      model.set(name);
    },

    addModel: function(model) {
      var EditView = require("modules/tables/views/EditView");
      var collection = this.relatedCollection;
      var view = new EditView({model: model, inModal: true});
      view.headerOptions.route.isOverlay = true;
      view.headerOptions.route.breadcrumbs = [];
      view.headerOptions.basicSave = true;

      view.events = {
        'click .saved-success': function() {
          this.save();
        },
        'click #removeOverlay': function() {
          app.router.removeOverlayPage(this);
        }
      };


      app.router.overlayPage(view);

      view.save = function() {
        model.set(view.editView.data());
        collection.add(model,{nest: true});
        app.router.removeOverlayPage(this);
      };
    },

    insertItem: function() {
      var collection = app.files;
      var view = new Overlays.ListSelect({collection: collection});
      app.router.overlayPage(view);

      var me = this;

      view.save = function() {
        _.each(view.table.selection(), function(id) {
          var data = collection.get(id).toJSON();
          me.relatedCollection.add(data, {parse: true, silent: true, nest: true});
        }, this);
        me.relatedCollection.trigger('add');
        app.router.removeOverlayPage(this);
      };

      collection.fetch();
    },

    editModel: function(model) {
      var EditView = require("modules/tables/views/EditView");
      var columnName = this.columnSchema.relationship.get('junction_key_right');
      var view = new EditView({model: model, hiddenFields: [columnName]});

      view.headerOptions.route.isOverlay = true;
      view.headerOptions.route.breadcrumbs = [];
      view.headerOptions.basicSave = true;

      view.events = {
        'click .saved-success': function() {
          this.save();
        },
        'click #removeOverlay': function() {
          app.router.removeOverlayPage(this);
        }
      };

      app.router.overlayPage(view);

      view.save = function() {
        model.set(model.diff(view.editView.data()));
        app.router.removeOverlayPage(this);
      };

      // Fetch first time to get the nested tables
      model.fetch();
    },

    drop: function() {
      var relatedCollection = this.model.get(this.name);

      this.$('.media-slideshow-item img').each(function(i) {
        relatedCollection.get($(this).attr('data-file-cid')).set({sort: i},{silent: true});
      });

      // There is no "saveAfterDrop" now, but we could use this for instant saving
      // if (this.options.saveAfterDrop) {
      //   relatedCollection.save({columns:['id','sort']});
      // }

      relatedCollection.setOrder('sort','ASC',{silent: true});
    },

    serialize: function() {
      var models = this.relatedCollection.models;
      var rows = [];
      var that = this;
      _.each(models, function(model) {
        if(model.get(app.statusMapping.status_name) != app.statusMapping.deleted_num) {
          var cid = model.cid;
          model = new app.files.model(model.get('data').attributes, {collection: that.relatedCollection});
          rows.push({id: model.id, url: model.makeFileUrl(true), cid:cid});
        }
      });

      var relatedCollection = this.model.get(this.name);
      var junctionStructure = relatedCollection.junctionStructure;
      var sortable = (junctionStructure.get('sort') !== undefined)? true : false;

      return {
        rows: rows,
        canEdit: this.canEdit,
        showChooseButton: this.showChooseButton && this.canEdit,
        showAddButton: this.showAddButton && this.canEdit,
        showRemoveButton: this.showRemoveButton && this.canEdit,
        sortable: sortable
      };
    },

    afterRender: function() {
      var $dropzone = this.$el;
      var model = this.fileModel;
      var self = this;
      var relatedCollection = this.model.get(this.name);
      var relatedSchema = relatedCollection.structure;
      var junctionStructure = relatedCollection.junctionStructure;

      // Since data transfer is not supported by jquery...
      // XHR2, FormData
      $dropzone[0].ondrop = _.bind(function(e) {
        e.stopPropagation();
        e.preventDefault();
        app.sendFiles(e.dataTransfer.files, function(data) {
          _.each(data, function(item) {
            item[app.statusMapping.status_name] = app.statusMapping.active_num;
            // Unset the model ID so that a new file record is created
            // (and the old file record isn't replaced w/ this data)
            item.id = undefined;
            item.user = self.userId;
            var model = new self.relatedCollection.nestedCollection.model(item, {collection: self.relatedCollection.nestedCollection, parse: true});
            model = new Backbone.Model({data: model}, {collection:self.relatedCollection});
            self.relatedCollection.add(model);
          });
        });
      });

      if(junctionStructure.get('sort') !== undefined) {
        // Drag and drop reordering
        var container = this.$el.find('.ui-file-container')[0];
        var that = this;
        var sort = new Sortable(container, {
          animation: 150, // ms, animation speed moving items when sorting, `0` — without animation
          draggable: ".media-slideshow-item", // Specifies which items inside the element should be sortable
          ghostClass: "sortable-file-ghost",
          onStart: function (evt) {
            //var dragItem = jQuery(evt.item);
            var jContainer = jQuery(container);
            jContainer.addClass('remove-hover-state');
          },
          onEnd: function (evt) {
            //var dragItem = jQuery(evt.item);
            var jContainer = jQuery(container);
            jContainer.removeClass('remove-hover-state');
          },
          onUpdate: function (evt){
            that.drop();
          }
        });
      }

    },

    initialize: function(options) {
      if (!this.columnSchema.relationship ||
           'MANYTOMANY' !== this.columnSchema.relationship.get('type')) {
        throw "The column " + this.columnSchema.id + " needs to have a relationship of the type MANYTOMANY in order to use the multiple_files ui";
      }

      var relatedCollection = this.model.get(this.name);
      var relatedSchema = relatedCollection.structure;
      var junctionStructure = relatedCollection.junctionStructure;
      var sortable = false;

      if(junctionStructure.get('sort') !== undefined) {
        sortable = true;
        relatedCollection.setOrder('sort','ASC');
      }

      this.canEdit = !(options.inModal || false);
      this.showRemoveButton = this.columnSchema.options.get('remove_button') === "1";
      this.showChooseButton = this.columnSchema.options.get('choose_button') === "1";
      this.showAddButton = this.columnSchema.options.get('add_button') === "1";
      this.sortable = sortable;

      this.relatedCollection = relatedCollection;
      this.listenTo(relatedCollection, 'change add remove', function() {
        this.render();
      }, this);

      this.listenTo(relatedCollection.nestedCollection, 'sync', function() {
      }, this);
    }
  });

  Module.list = function() {
    return 'x';
  };

  return Module;
});