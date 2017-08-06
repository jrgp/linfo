const gulp = require('gulp');
const gulpif = require('gulp-if');
const sourcemaps = require('gulp-sourcemaps');
const pump = require('pump');
const util = require('gulp-util');
const production = (util.env.type === 'production');

gulp.task('scripts', cb => {
	const uglify = require('gulp-uglify');
	const rename = require('gulp-rename');
	
	pump([
		gulp.src(['layout/scripts.js']),
		gulpif(production, uglify()),
		rename({ suffix: '.min' }),
		gulp.dest('layout'),
	], cb);
});

gulp.task('styles', cb => {
	const sass = require('gulp-sass');
    
	pump([
		gulp.src('./layout/sass/**/[^_]*.sass'),
		gulpif(!production, sourcemaps.init()),
		sass({
			outputStyle: (production) ? 'compressed' : 'expanded',
		}),
		gulpif(!production, sourcemaps.write()),
		gulp.dest('layout'),
	], cb);
});

gulp.task('default', ['scripts', 'styles']);
