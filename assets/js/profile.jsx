import React from 'react';
import { createRoot } from 'react-dom/client';
import ProfilePictureUploader from './components/Picture/ProfilePictureUploader';

const container = document.getElementById('root');
const root = createRoot(container);
root.render(<ProfilePictureUploader></ProfilePictureUploader>);
