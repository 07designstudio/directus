define([
  "app",
  "backbone",
  "core/collection",
  "core/entries/entries.model"
],

function(app, Backbone, Collection, EntriesModel) {

  var EntriesCollection = Collection.extend({

    model: EntriesModel,

    toJSON: function(options) {
      options = options || {};
      var result = EntriesCollection.__super__.toJSON.apply(this, [options]);
      if (options.changed) {
        result = _.filter(result, function(obj) { return !_.isEmpty(obj); });
      }
      return result;
    },

    getColumns: function() {
      return (this.filters.columns !== undefined) ? this.filters.columns : _.intersection(this.structure.pluck('id'), this.preferences.get('columns_visible').split(','));
    },

    getFilter: function(key) {
      return (this.preferences && this.preferences.has(key)) ? this.preferences.get(key) : this.filters[key];
    },

    getFilters: function() {
      return _.extend(this.filters, _.pick(this.preferences.toJSON(),'columns_visible','sort','sort_order','active'));
    },

    setFilter: function(key, value, options) {
      var attrs, preferencesHasChanged = false;
      if (key === null || typeof key === 'object') {
        attrs = key;
      } else {
        (attrs = {})[key] = value;
      }
      _.each(attrs, function(value, key) {
        if (this.preferences && this.preferences.has(key)) {
          preferencesHasChanged = true;
          this.preferences.set(key, value, {silent: true});
        } else {
          this.filters[key] = value;
        }
      },this);
      if (preferencesHasChanged) this.preferences.save();
    },

    hasColumn: function(columnName) {
      return this.structure.get(columnName) !== undefined
    },

    hasPermission: function(permissionType) {
      var permissions = this.privileges.get('permissions') || '';
      permissionsArray = permissions.split(',');
      return _.contains(permissionsArray, permissionType);
    },

    isWriteBlacklisted: function(attribute) {
      var writeBlacklist = (this.privileges.get('write_field_blacklist') || '').split(',');
      return _.contains(writeBlacklist, attribute);
    },

    initialize: function(models, options) {
      var rowsPerPage = parseInt(app.settings.get('global').get('rows_per_page'),10) || 500;
      this.structure = options.structure;
      this.privileges = options.privileges;
      this.table = options.table;
      this.active = this.table.get('active');
      this.url = options.url || this.table.get('url') + '/rows';
      this.filters = _.extend({ currentPage: 0, perPage: rowsPerPage, sort: 'id', sort_order: 'ASC', active: '1,2' }, options.filters);
      if (options.preferences) {
        this.preferences = options.preferences;
        this.preferences.on('change', function() { this.trigger('change'); }, this);
      }
    },

    parse: function(response) {

      if (_.isEmpty(response)) return;

      if (response.total !== undefined) {
        this.table.set('total', response.total, {silent: true});
      }

      if (response.active !== undefined) {
        this.table.set('active', response.active, {silent: true});
      }

      if (response.inactive !== undefined) {
        this.table.set('inactive', response.inactive, {silent: true});
      }

      if (response.trash !== undefined) {
        this.table.set('trash', response.trash, {silent: true});
      }

      return response.rows;
    }

  });

  return EntriesCollection;

});