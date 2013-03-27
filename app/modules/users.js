define([
  "app",
  "backbone",
  "core/directus"
],

function(app, Backbone, Directus) {

  var Users = app.module();

  var SaveModule = Backbone.Layout.extend({
    template: 'module-save',
    attributes: {'class': 'directus-module'},
    serialize: function() {
      return {
        isNew: (this.model.id === undefined),
        showActive: true,
        isActive: this.model.isNew() || (this.model.get('active') === 1),
        isInactive: (this.model.get('active') === 2)
      };
    },
    initialize: function() {
      this.model.on('sync', this.render, this);
    }
  });


  Users.Views.Edit = Backbone.Layout.extend({

    template: 'page',

    events: {
      'click .btn-primary': function(e) {
        var data = $('form').serializeObject();
        data.active = $('input[name=active]:checked').val();
        this.model.save(data, {
          success: function() { app.router.go('#users'); },
          error: function() { console.log('error',arguments); }
        });
      }
    },

    serialize: function() {
      var breadcrumbs = [{ title: 'Users', anchor: '#users'}];
      var title = (this.model.id) ? this.model.get('first_name') + ' ' + this.model.get('last_name') : 'New User';

      return {
        breadcrumbs: breadcrumbs,
        title: title,
        sidebar: true
      };
    },

    beforeRender: function() {
      this.setView('#page-content', new Directus.EditView({model: this.model}));
      this.setView('#sidebar', new SaveModule({model: this.model}));
    }
  });

  var ListView = Directus.Table.extend({
    navigate: function(id) {
      app.router.go('#users', id);
      //app.router.navigate('#users/' + id);
      //app.router.setPage(Users.Views.Edit, {model: this.collection.get(id)});
    }
  });

  Users.Views.List = Backbone.Layout.extend({

    template: 'page',

    serialize: function() {
      return {title: 'Users', buttonTitle: 'Add New User'};
    },

    events: {
      'click #btn-top': function() {
        app.router.go('#users','new');
      }
    },

    afterRender: function() {
      this.setView('#page-content', this.table);
      this.collection.fetch();
    },

    initialize: function() {
      this.table = new ListView({collection:this.collection, toolbar: false, navigate: true, selectable:false});
    }
  });

  return Users;
});