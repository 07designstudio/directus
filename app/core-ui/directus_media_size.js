//  Directus User List View component
//  Directus 6.0

//  (c) RANGER
//  Directus may be freely distributed under the GNU license.
//  For all details and documentation:
//  http://www.getdirectus.com

define(['app','backbone'], function(app, Backbone) {

	var Module = {};

  Module.id = 'directus_media_size';
  Module.system = true;

  Module.options = {};

  Module.list = function(options) {
    return app.bytesToSize(options.value);
  };

  return Module;

});