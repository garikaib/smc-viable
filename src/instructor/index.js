import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

import CourseBuilderApp from './CourseBuilderApp';

document.addEventListener('DOMContentLoaded', () => {
    // Original Instructor Hub Root
    const instructorRoot = document.getElementById('smc-instructor-root');
    if (instructorRoot) {
        createRoot(instructorRoot).render(<App />);
    }

    // New Course Builder Root
    const builderRoot = document.getElementById('smc-course-builder-root');
    if (builderRoot) {
        createRoot(builderRoot).render(<CourseBuilderApp />);
    }
});
