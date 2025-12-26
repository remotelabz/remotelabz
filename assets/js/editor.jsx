import React from 'react';
import { createRoot } from 'react-dom/client';
import Editor from './components/Editor/Editor';

const container = document.getElementById('labEditor');
const root = createRoot(container);
root.render(<Editor></Editor>);
