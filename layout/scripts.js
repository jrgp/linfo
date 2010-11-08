/**
 * This file is part of Linfo (c) 2010 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo.  If not, see <http://www.gnu.org/licenses/>.
 * 
*/

/**
 * Manages all Linfo javascript
 * @author Lee Bradley (elephanthunter)
 * 
 * Goals:
 *  - Keep the global scope squeaky clean (and, as a direct result, compression efficient)
 *  - Keep performance blazing fast
 */
var Linfo = (function() {
	/**
	 * Set the opacity of an element
	 * @param el the element to set
	 * @param opacity the opacity to set it to (0.0 - 1.0)
	 */
	function setOpacity(el, opacity) {
		el.style.opacity = opacity;

		// IE / Windows
		el.style.filter = "alpha(opacity=" + (opacity * 100) + ")";
	}

	/**
	 * Call a function repeatedly for a specified duration
	 * @param fn the function to call
	 * @param timeout when to quit
	 * @param fnComplete the function to call when finished (optional)
	 */
	function callCountdown(fn, timeout, fnComplete) {
		var interval = 10,
			time = 0,
			iFinishTime = timeout - (timeout % interval),
			fnCallback = function() {
				var iPercentage = (time++ * interval) / iFinishTime;
				fn(iPercentage);
				if (iPercentage >= 1) {
					if (fnComplete) fnComplete();
					return;
				}
				setTimeout(fnCallback, interval);
			};

		fnCallback();
	}

	/**
	 * Slide an element to the specified height
	 * @param el the element to slide
	 * @param iEndHeight the end height
	 * @param fnCallback the function to call when finished (optional)
	 * @param time the duration of the animation (optional)
	 */
	function slideTo(el, iEndHeight, fnCallback, time) {
		var iStartHeight = el.offsetHeight,
			iHeightDiff = iStartHeight - iEndHeight;

		callCountdown(
			function(i) {
				var iCurrentHeight = ((1 - i) * iHeightDiff) + iEndHeight;
				el.style.height = iCurrentHeight.toString() + 'px';
			},
			time || 100,
			fnCallback
		);
	};

	/**
	 * Fade an element in
	 * @param el the element to fade
	 * @param fnCallback the function to call when finished (optional)
	 * @param time the duration of the animation (optional)
	 */
	function fadeIn(el, fnCallback, time) {
		callCountdown(
			function(i) { setOpacity(el, i); },
			time || 100,
			fnCallback
		);
	};
	
	/**
	 * Fade an element out
	 * @param el the element to fade
	 * @param fnCallback the function to call when finished (optional)
	 * @param time the duration of the animation (optional)
	 */
	function fadeOut(el, fnCallback, time) {
		callCountdown(
			function(i) { setOpacity(el, 1 - i); },
			time || 200,
			fnCallback
		);
	};
	
	/**
	 * Toggle the display of a collapsable Linfo bar
	 * @param e the event object
	 */
	function toggleShow(e) {
		var elButton = e.target || e.srcElement,
			elInfoTable = elButton.parentNode;

		// Make sure we're not on already sliding
		if (elInfoTable.sliding) return;
		elInfoTable.sliding = true;

		// Get the information table
		var elTable = elInfoTable.getElementsByTagName('table')[0];

		if (elInfoTable.className === "infoTable") {
			elButton.innerHTML = "+";

			// Fade out, then slide up
			fadeOut(elTable, function() {
				elInfoTable.fullSize = elInfoTable.offsetHeight;
				slideTo(elInfoTable, elInfoTable.offsetHeight - elTable.offsetHeight, function() {
					elInfoTable.sliding = false;
				});
				elInfoTable.className = "infoTable collapsed";
			});
		} else {
			elInfoTable.className = "infoTable";
			elButton.innerHTML = "-";

			// Slide down, then fade in
			slideTo(elInfoTable, elInfoTable.fullSize, function() {
				elInfoTable.style.height = "";
				fadeIn(elTable, function() {
					elInfoTable.sliding = false;
				});
			});
		}
	}

	return {
		toggleShow: toggleShow
	};
}());
