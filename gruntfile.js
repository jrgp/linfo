module.exports = function(grunt) {
    'use strict';

	function loadConfig(path) {
		var glob = require('glob'),
			object = {},
			key;
		glob.sync('*', {cwd: path}).forEach(function(option) {
			key = option.replace(/\.js$/,'');
			object[key] = require(path + option);
		});
		return object;
	}



    // Base Config
    var config = {

        pkg: grunt.file.readJSON('package.json'),

        build: {
            buildId: '<%= pkg.version %>.<%= grunt.template.today("yyyymmddHHMM") %>',
            temp: '_tmp',
            source: '',    //'src'
            dest: '',      //'dist/<%= build.buildId %>'
            dest_dev: '',  //'dev',
            banner: '/*! <%= pkg.title || pkg.name %> - v<%= pkg.version %> - ' +
                    '<%= grunt.template.today("yyyy-mm-dd") %>\r\n' +
                    '<%= pkg.homepage ? " * " + pkg.homepage + "\\r\\n" : "" %>' +
                    ' * Copyright © <%= grunt.template.today("yyyy") %> <%= pkg.author.name %>;' +
                    ' Licensed <%= _.pluck(pkg.licenses, "type").join(", ") + "\\r\\n" %> */\r\n'
        },

		uglify: {
			dist: {
				options: {sourceMap: false},
				files: {'layout/scripts.min.js': ['layout/scripts.js']}
			},
			dev: {
				options: {sourceMap: true},
				files: {'layout/scripts.min.js': ['layout/scripts.js']}
			}			
		},	
		
		sass: {
			dist: {
				options: {
					sourceMap:false,
					style: 'compressed'
					},
				files: [{
					expand: true,
					cwd: 'layout/sass',
					src: ['theme_default.sass','theme_neuro.sass','theme_frogg.sass','theme_dark.sass','theme_stb.sass','theme_photon.sass','mobile.sass','icons.sass'],
					dest: 'layout',
					ext: '.css'
				}]
			},
			dev: {
				options: {
					sourceMap:true,
					style: 'expanded', //nested, compact, compressed, expanded
					banner: '<%= build.banner %>'
				},
				files: [{
					expand: true,
					cwd: 'layout/sass',
					src: ['theme_default.sass','theme_neuro.sass','theme_frogg.sass','theme_dark.sass','theme_stb.sass','theme_photon.sass','mobile.sass','icons.sass'], // not *.sass due to _icons & _base
					dest: 'layout',
					ext: '.css'
				}]
			}
        }
    };

	// Look for any option files inside of `/custom/grunt_tasks` folder.
	// The file name would be `sass.js` or `watch.js` etc
	// If found, extend and overwrite with custom one
	grunt.util._.extend(config, loadConfig('./custom/grunt_tasks/'));

	// Config the Options
	grunt.initConfig(config);

	// Load the Tasks
	require('load-grunt-tasks')(grunt);

	// Register Tasks
	grunt.registerTask('default', [ 'sass:dist','uglify:dist' ]); // Default Production Build

	grunt.registerTask('dev', [ 'sass:dev','uglify:dev' ]);

};
