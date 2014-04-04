//  router.js
//  Directus 6.0

//  (c) RANGER
//  Directus may be freely distributed under the GNU license.
//  For all details and documentation:
//  http://www.getdirectus.com

define(function(require, exports, module) {

  "use strict";

  var app              = require('app'),
      //Directus       = require('core/directus'),
      Tabs             = require('core/tabs'),
      Bookmarks        = require('core/bookmarks'),
      SchemaManager    = require('schema/SchemaManager'),
      EntriesManager   = require('core/EntriesManager'),
      ExtensionManager = require('core/ExtensionManager'),
      Activity         = require('modules/activity/activity'),
      Table            = require('modules/tables/table'),
      Settings         = require('modules/settings/settings'),
      Media            = require('modules/media/media'),
      Users            = require('modules/users/users'),
      Messages         = require('modules/messages/messages'),
      moment           = require('moment');

  var Router = Backbone.Router.extend({

    routes: {
      "":                               "tables",
      "tables":                         "tables",
      "tables/:name(/pref/:pref)":      "entries",
      "tables/:name/:id":               "entry",
      "activity":                       "activity",
      "media":                          "media",
      "media/:id":                      "mediaItem",
      "users":                          "users",
      "users/:id":                      "user",
      "settings":                       "settings",
      "settings/:name":                 "settings",
      "settings/tables/:table":         "settingsTable",
      "settings/permissions/:groupId":  "settingsPermissions",
      "messages":                       "messages",
      "messages/new":                   "newMessage",
      "messages/:id":                   "message",
      "cashregister":                   "cashregister",
      "booker":                         "booker",
      '*notFound':                      "notFound"
    },

    go: function() {
      var array = _.isArray(arguments[0]) ? arguments[0] : _.toArray(arguments);
      return this.navigate(array.join("/"), true);
    },

    setTitle: function(title) {
      document.title = title;
    },

    showAlert: function(message, type) {
      if (!this.alert) {
        this.alert = new Backbone.Layout({template: 'alert', serialize: {message: message, type: type}});
        this.v.messages.insertView(this.alert).render();
      }
    },

    hideAlert: function() {
      if (this.alert) {
        this.alert.remove();
        this.alert = undefined;
      }
    },

    notFound: function() {
      this.setTitle('404');
      this.v.main.setView('#content', new Backbone.Layout({template: Handlebars.compile('<h1>Not found</h1>')}));
      this.v.main.render();
    },

    openModal: function(view, options) {
      //options.view = view;
      //var modal = new Modal(options);
      //this.v.messages.insertView(modal).render();
      //return modal;
    },

    overlayPage: function(view) {
      if(this.v.main.getViews('#content')._wrapped.length <= 1) {
        this.baseRouteSave = Backbone.history.fragment;
      }

      this.v.main.getViews('#content').each(function(view) {
        view.$el.hide();
      });
      this.v.main.insertView('#content', view).render();

      var that=this;
      this.oldLoadUrlFunction = Backbone.History.prototype.loadUrl;
      Backbone.History.prototype.loadUrl = function() {
        if(that.baseRouteSave == this.getFragment() || window.confirm("All Unsaved changes will be lost, Are you sure you want to leave?")) {
          return that.oldLoadUrlFunction.apply(this, arguments);
        } else {
          this.navigate(that.baseRouteSave);
          that.navigate(that.baseRouteSave);
          return true;
        }
      };
    },

    removeOverlayPage: function(view) {
      view.remove(); //Remove Overlay Page
      var vieww = this.v.main.getViews('#content').last()._wrapped;
      vieww.$el.show();

      console.log(this.v.main.getViews('#content')._wrapped.length);

      if(this.v.main.getViews('#content')._wrapped.length <= 1) {
        Backbone.History.prototype.loadUrl = this.oldLoadUrlFunction;
        this.navigate(this.baseRouteSave);
        this.baseRouteSave = undefined;
      }
    },

    setPage: function(View, options) {
      this.v.main.setView('#content', new View(options)).render();
    },

    tables: function() {
      if (_.contains(this.tabBlacklist,'tables'))
        return this.notFound();

      this.navigate('/tables'); //If going to / rewrite to tables

      this.setTitle('Tables');
      this.tabs.setActive('tables');
      this.v.main.setView('#content', new Table.Views.Tables({collection: SchemaManager.getTables()}));
      this.v.main.render();
    },

    entries: function(tableName, pref) {
      if (_.contains(this.tabBlacklist,'tables'))
        return this.notFound();

      var collection;

      if (!SchemaManager.getTable(tableName)) {
        return this.notFound();
      }

      // see if the collection is cached...
      if (this.currentCollection !== undefined && this.currentCollection.table.id == tableName) {
        collection = this.currentCollection;
      } else {
        collection = EntriesManager.getInstance(tableName);
      }



      if (collection.table.get('single')) {
        if(collection.models.length) {
          this.entry(tableName, collection.models[0].get('id'));
        } else {
          // Fetch collection so we know the ID of the "single" row
          collection.once('sync', _.bind(function(collection, xhr, status){
            if(0 === collection.length) {
              // Add new form
              this.router.entry(tableName, "new");
            } else {
              // Edit first model
              var model = collection.models[0];
              this.router.entry(tableName, model.get('id'));
            }
          }, {router:this}));
          collection.fetch();
        }
        return;
      }

      if(pref) {
        this.loadedPreference = pref;
        this.navigate("/tables/" + tableName);
      }

      // Cache collection for next route
      this.currentCollection = collection;
      this.bookmarks.setActive('tables');
      this.tabs.setActive('tables');

      this.v.main.setView('#content', new Table.Views.List({collection: collection}));
      this.v.main.render();
    },

    entry: function(tableName, id) {
      if (_.contains(this.tabBlacklist,'tables'))
        return this.notFound();

      this.setTitle('Tables');
      this.bookmarks.setActive('tables');
      this.tabs.setActive('tables');

      var isBatchEdit = (typeof id === 'string') && id.indexOf(',') !== -1,
          collection,
          model,
          view;

      // see if the collection is cached...
      if (this.currentCollection !== undefined && this.currentCollection.table.id == tableName) {
        collection = this.currentCollection;
      } else {
        collection = EntriesManager.getInstance(tableName);
      }

      if (collection === undefined) {
        return this.notFound();
      }

      if (id === "new" || isBatchEdit) {
        // Passing parse:true will setup relations
        model = new collection.model({}, {collection: collection, parse: true});

      } else {
        model = collection.get(id);
        if (model === undefined) {
          model = new collection.model({id: id}, {collection: collection, parse: true});
        }
      }

      if (isBatchEdit) {
        view = new Table.Views.BatchEdit({model: model, batchIds: id.split(',')});
      } else {
        view = new Table.Views.Edit({model: model});
      }

      this.v.main.setView('#content', view);
      this.v.main.render();
    },

    activity: function() {
      if (_.contains(this.tabBlacklist,'activity'))
        return this.notFound();

      this.setTitle('Activity');
      this.tabs.setActive('activity');
      this.v.main.setView('#content', new Activity.Views.List({collection: app.activity}));
      this.v.main.render();
    },

    media: function() {
      if (_.contains(this.tabBlacklist,'media'))
        return this.notFound();

      this.setTitle('Media');
      this.tabs.setActive('media');
      this.v.main.setView('#content', new Media.Views.List({collection: app.media}));
      this.v.main.render();
    },

    mediaItem: function(id) {
      var mediaView = new Media.Views.List({collection: app.media});
      var model = app.media.get(id);

      if (model === undefined) {
        model = new app.media.model({id: id}, {collection: app.media});
      }

      mediaView.addEditMedia(model, 'Editing media');

      this.setTitle('Media');
      this.tabs.setActive('media');
      this.v.main.setView('#content', mediaView);
      this.v.main.render();

    },

    users: function() {
      this.setTitle('Users');
      this.tabs.setActive('users');
      this.v.main.setView('#content', new Users.Views.List({collection: app.users}));
      this.v.main.render();
    },

    user: function(id) {
      var user = app.users.getCurrentUser();
      var userGroup = user.get('group');

      if (!(parseInt(id,10) === user.id || userGroup.id === 0)) {
        return this.notFound();
      }

      var model;
      this.setTitle('Users');
      this.tabs.setActive('users');

      if (id === "new") {
        model = new app.users.model({}, {collection: app.users, parse:true});
      } else {
        model = app.users.get(id);
      }
      this.v.main.setView('#content', new Users.Views.Edit({model: model}));
      this.v.main.render();
    },

    settings: function(name) {
      if (_.contains(this.tabBlacklist,'settings'))
        return this.notFound();

      this.setTitle('Settings');
      this.tabs.setActive('settings');

      switch(name) {
        case 'tables':
          this.v.main.setView('#content', new Settings.Tables({collection: SchemaManager.getTables()}));
          break;
        case 'global':
          this.v.main.setView('#content', new Settings.Global({model: app.settings.get('global'), title: 'Global', structure: SchemaManager.getColumns('settings', 'global')}));
          break;
        case 'media':
          this.v.main.setView('#content', new Settings.Global({model: app.settings.get('media'), title: 'Media', structure: SchemaManager.getColumns('settings', 'media')}));
          break;
        case 'permissions':
          this.v.main.setView('#content', new Settings.Permissions({collection: app.groups}));
          break;
        case 'system':
          this.v.main.setView('#content', new Settings.System());
          break;
        case 'about':
          this.v.main.setView('#content', new Settings.About());
          break;
        default:
          this.v.main.setView('#content', new Settings.Main({tables: SchemaManager.getTables()}));
          break;
      }

      this.v.main.render();
    },

    settingsTable: function(tableName) {
      if (_.contains(this.tabBlacklist,'settings'))
        return this.notFound();

      this.setTitle('Settings');
      this.tabs.setActive('settings');

      this.v.main.setView('#content', new Settings.Table({model: SchemaManager.getTable(tableName)}));

      this.v.main.render();
    },

    settingsPermissions: function(groupId) {
      if (_.contains(this.tabBlacklist,'settings'))
        return this.notFound();

      this.setTitle('Settings - Permissions');
      this.tabs.setActive('settings');
      var collection = new Settings.GroupPermissions.Collection([], {url: app.API_URL + 'privileges/'+groupId});
      this.v.main.setView('#content', new Settings.GroupPermissions.Page({collection: collection, title: app.groups.get(groupId).get('name')}));
      this.v.main.render();
    },

    messages: function(name) {
      this.tabs.setActive("messages");
      this.v.main.setView('#content', new Messages.Views.List({collection: app.messages}));
      this.v.main.render();
    },

    message: function(id) {
      var model = app.messages.get(id);
      this.setTitle('Message');

      if (model === undefined) {
        model = new app.messages.model({id: id}, {collection: app.messages, parse: true});
        model.fetch();
      }

      this.v.main.setView('#content', new Messages.Views.Read({model: model}));
      this.v.main.render();
    },

    newMessage: function() {

      var model = new app.messages.model({from: app.users.getCurrentUser().id}, {collection: app.messages, parse: true});

      this.v.main.setView('#content', new Messages.Views.New({model: model}));
      this.v.main.render();
    },

    initialize: function(options) {

      this.tabBlacklist = (options.tabPrivileges.tab_blacklist || '').split(',');

      //Fade out and remove splash
      $('#splash').fadeOut('fast').remove();
      this.tabs = options.tabs;
      this.bookmarks = app.getBookmarks();
      this.extensions = {};

      _.each(options.extensions, function(item) {
        try {
          this.extensions[item] = ExtensionManager.getInstance(item);
        } catch (e) {
          console.log(item + ' failed to load:', e.stack);
          this.tabs.get(item).set({'error': e});
          return;
        }
        //this.extensions[item.id].bind('all', logRoute);
        this.extensions[item].on('route', function() {
          this.trigger('subroute',item);
          this.trigger('route:'+item,item);
        }, this);
        //this.tabs.add({title: app.capitalize(item.id), id: item.id, extension: true});
      }, this);

      var user = app.users.getCurrentUser();
      var tabs = new Tabs.View({collection: this.tabs});

      var bookmarks = new Bookmarks.View({collection: this.bookmarks});

      //Top
      var Navbar = Backbone.Layout.extend(
      {

        template: "navbar",

        tagName: 'div',

        serialize: function() {
          return {
            siteUrl: this.model.get('site_url'),
            messageCounter: app.messages.unread,
            cms_thumbnail_url: this.model.get('cms_thumbnail_url')
          };
        },
        beforeRender: function() {
          this.insertView('#featureSidebar', tabs);
          this.insertView('#mainSidebar', bookmarks);
        },

        keep: true
      });

      // Update unread message counter
      app.messages.on('sync', function(collection, model) {
        $('.unread-messages-counter').html(app.messages.unread);
      });

      //holds references to view instances
      this.v = {};
      var nav = new Navbar({model: app.settings.get('global')});

      //var nav = new Navbar({model: app.settings.get('global'), collection: this.tabs});
      this.v.main = new Backbone.Layout({

        el: "#main",

        views: {
          '#sidebar': nav
        }

      });

      this.v.messages = new Backbone.Layout({
        el: "#messages"
      });

      this.on('subroute', function(id, router) {
        this.tabs.setActive(id);
      });


      this.bind("all", function(route, router){
        var last_page;
        var routeTokens = route.split(':');
        if(routeTokens.length > 1) {
          // Report the "last page" data to the API
          // @fixes https://github.com/RNGR/directus6/issues/199

          var user = app.users.getCurrentUser();

          var currentPath = window.location.pathname.substring(app.root.length);
          if(currentPath.length) {
            bookmarks.setActive(currentPath);

            last_page = JSON.stringify({
              'path' : currentPath,
              'route' : route.substring(6),
              'param' : router
            });

            user.save({'last_page': last_page, 'last_access': moment().format('YYYY-MM-DD HH:mm')}, {
              patch: true,
              global: false,
              silent: true,
              wait: true,
              validate: false,
              url: user.url() + "?skip_activity_log=1"
            });

          } else {
            // If theere's no path in the location (i.e. the user just logged in),
            // take them to their last visited page, defaulting to "tables".
            /*var authenticatedUser = app.getCurrentUser();
            user = app.users.get(authenticatedUser.id);
            last_page = $.parseJSON(user.get('last_page'));

            if(_.isEmpty(last_page)) {
              last_page = {};
            }
            if(_.isEmpty(last_page.path)) {
              last_page.path = 'tables';
            }
            this.navigate(last_page.path, {trigger: true});*/
          }
        }
      });

      this.v.main.render();
    }
  });

  return Router;

});