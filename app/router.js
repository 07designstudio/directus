//  router.js
//  Directus 6.0

//  (c) RANGER
//  Directus may be freely distributed under the GNU license.
//  For all details and documentation:
//  http://www.getdirectus.com

define([
  "app",
  "core/directus",
  "core/tabs",
  "core/ui",
  "modules/activity",
  "modules/table",
  "modules/settings",
  "modules/media",
  "modules/users",
  "modules/messages",
  "core/modal",
  "core/collection.settings",
  "core/extensions"
],

function(app, Directus, Tabs, UI, Activity, Table, Settings, Media, Users, Messages, Modal, CollectionSettings, extensions) {

  var Router = Backbone.Router.extend({

    routes: {
      "":                               "index",
      "tables":                         "tables",
      "tables/:name":                   "entries",
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
      "cashregister":                   "cashregister"
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

    openModal: function(view, options) {
      options.view = view;
      var modal = new Directus.Modal(options);
      this.v.messages.insertView(modal).render();
      return modal;
    },

    setPage: function(view, options) {
      options.ui = UI;
      this.v.main.setView('#content', new view(options)).render();
    },

    tables: function() {
      this.setTitle('Tables');
      this.tabs.setActive('tables');
      this.v.main.setView('#content', new Table.Views.Tables({collection: app.tables}));
      this.v.main.render();
    },

    entries: function(tableName) {
      this.setTitle('Tables');
      var collection = app.entries[tableName];
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
      this.tabs.setActive('tables');
      this.v.main.setView('#content', new Table.Views.List({collection: app.entries[tableName]}));
      this.v.main.render();
    },

    entry: function(tableName, id) {
      this.setTitle('Tables');
      this.tabs.setActive('tables');
      var collection = app.entries[tableName];
      var model;

      if (id === "new") {
        // Passing parse:true will setup relations
        model = new collection.model({},{collection: collection, parse: true});

      } else {
        model = collection.get(id);
        if (model === undefined) {
          model = new collection.model({id: id}, {collection: collection});
        }
      }

      this.v.main.setView('#content', new Table.Views.Edit({model: model}));
      this.v.main.render();
    },

    activity: function() {
      this.setTitle('Activity');
      this.tabs.setActive('activity');
      this.v.main.setView('#content', new Activity.Views.List({collection: app.activity}));
      this.v.main.render();
    },

    media: function() {
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
      var model;
      this.setTitle('Users');
      this.tabs.setActive('users');

      if (id === "new") {
        model = new app.users.model({}, {collection: app.users});
      } else {
        model = app.users.get(id);
      }
      this.v.main.setView('#content', new Users.Views.Edit({model: model}));
      this.v.main.render();
    },

    settings: function(name) {
      this.setTitle('Settings');
      this.tabs.setActive('settings');

      switch(name) {
        case 'tables':
          this.v.main.setView('#content', new Settings.Tables({collection: app.tables}));
          break;
        case 'global':
          this.v.main.setView('#content', new Settings.Global({model: app.settings.get('global'), structure: Settings.GlobalStructure, title: 'Global'}));
          break;
        case 'media':
          this.v.main.setView('#content', new Settings.Global({model: app.settings.get('media'), structure: Settings.MediaStructure, title: 'Media'}));
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
          this.v.main.setView('#content', new Settings.Main({tables: app.tables}));
          break;
      }

      this.v.main.render();
    },

    settingsTable: function(tableName) {

      this.setTitle('Settings');
      this.tabs.setActive('settings');

      this.v.main.setView('#content', new Settings.Table({model: app.tables.get(tableName)}));

      this.v.main.render();
    },

    settingsPermissions: function(groupId) {
      this.setTitle('Settings - Permissions');
      this.tabs.setActive('settings');
      this.v.main.setView('#content', new Settings.GroupPermissions());
      this.v.main.render();
    },

    messages: function(name) {
      this.setPage(Messages.Views.List, {collection: this.messages});
    },

    initialize: function(options) {

      this.tabs = new Tabs.Collection([
        {title: "Activity", id: "activity", count: app.activity.table.get('active')},
        {title: "Tables", id: "tables", count: app.tables.getRows().length},
        {title: "Media", id: "media", count: app.media.table.get('active')},
        {title: "Users", id: "users", count: app.users.table.get('active')},
        {title: "Settings", id: "settings"}
      ]);

      this.extensions = {};

      _.each(extensions, function(item) {
        this.extensions[item.id] = new item.Router(item.id);
        this.extensions[item.id].on('route', function() {
          this.trigger('subroute',item.id);
        }, this);
        this.tabs.add({title: app.capitalize(item.id), id: item.id, extension: true});
      }, this);

      var tabs = this.tabs;

      var user = app.getCurrentUser();

      //Top
      var Navbar = Backbone.Layout.extend(
        {
          template: "navbar",
          serialize: function() {
            return {
              user: user.toJSON(),
              siteName: this.model.get('site_name'),
              siteUrl: this.model.get('site_url')
            };
          }
      });

      //holds references to view instances
      this.v = {};
      this.v.content = undefined;

      var tabs = new Tabs.View({collection: this.tabs})

      this.v.main = new Backbone.Layout({
        el: "#main",
        //template: "main",
        views: {
          '#navbar': new Navbar({model: app.settings.get('global')}),
          '#tabs': tabs
        }
      });

      this.v.messages = new Backbone.Layout({
        el: "#messages"
      });

      // Update media counter

      app.media.on('sync add', function() {
        // console.log('ssssync');
        // console.log(app.media.table.get('active'), app.media);
        this.tabs.get('media').set({count: app.media.table.get('active')});
        tabs.render();
      }, this);

      this.v.main.render();

      this.on('subroute', function(id) {
        this.tabs.setActive(id);
      });

      this.bind("all", function(route, router){
        // console.log('route change',route,router);
        var routeTokens = route.split(':');
        if(routeTokens.length > 1) {
          // Report the "last page" data to the API
          // @fixes https://github.com/RNGR/directus6/issues/199
          // @todo is this the right way to do this?
          var user = app.getCurrentUser();
          var currentPath = window.location.pathname.substring(app.root.length);
          if(currentPath.length) {
            var last_page = {
              'path' : currentPath,
              'route' : route.substring(6),
              'param' : router
            };
            user.save({'last_page': last_page}, {
              patch: true,
              url: user.url + "/" + user.id + "?skip_activity_log=1"
            });
            // didn't work:
            //     user.set('last_page', route);
            //     user.save();
            // nor:
            //     user.set('last_page', route);
            //     app.users.save();
          } else {
            // If theere's no path in the location (i.e. the user just logged in),
            // take them to their last visited page, defaulting to "tables".
            var authenticatedUser = app.getCurrentUser();
            var user = app.users.get(authenticatedUser.id);
            var last_page = $.parseJSON(user.get('last_page'));
            if(undefined === last_page.path || '' == last_page.path) {
              last_page.path = 'tables';
            }
            this.navigate(last_page.path, {trigger: true});
          }
        }
      });

    }
  });

  return Router;

});