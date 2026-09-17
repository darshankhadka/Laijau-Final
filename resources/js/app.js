import Alpine from 'alpinejs';
import { initStore } from './store';

window.Alpine = Alpine;
initStore(Alpine);
Alpine.start();
