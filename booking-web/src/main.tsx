import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './styles/public.css';
import './styles/booking.css';
import { applyCachedTheme } from './theme';

// Brand colours of the last visit before the first paint (src/theme.ts).
applyCachedTheme();

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
