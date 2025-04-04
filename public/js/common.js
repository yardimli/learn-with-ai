console.log("Common JS loaded.");

document.addEventListener('DOMContentLoaded', () => {
	const darkModeSwitch = document.getElementById('darkModeSwitch');
	const htmlElement = document.documentElement; // Target <html> for the class
	const moonIcon = document.getElementById('darkModeIconMoon');
	const sunIcon = document.getElementById('darkModeIconSun');
	
	if (!darkModeSwitch || !htmlElement || !moonIcon || !sunIcon) {
		console.error("Dark mode switch elements not found!");
		return;
	}
	
	// Function to update icon visibility
	const updateIcons = (isDarkMode) => {
		moonIcon.classList.toggle('d-none', isDarkMode);
		sunIcon.classList.toggle('d-none', !isDarkMode);
	};
	
	// Set initial switch state and icons based on localStorage/class on <html>
	const isCurrentlyDark = htmlElement.classList.contains('dark-mode');
	darkModeSwitch.checked = isCurrentlyDark;
	updateIcons(isCurrentlyDark);
	
	// Add event listener
	darkModeSwitch.addEventListener('change', (event) => {
		const isDarkMode = event.target.checked;
		htmlElement.classList.toggle('dark-mode', isDarkMode);
		localStorage.setItem('darkModeEnabled', isDarkMode);
		updateIcons(isDarkMode);
	});
});
