# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a single-page web application that displays an interactive map of World War 2 bunkers in the Nederhorst region using OpenStreetMap and Leaflet.js. The application allows authenticated users to add photo markers to the map and view existing bunker locations.

## Architecture

- **Single HTML file**: The entire application is contained in `index.html` with embedded CSS and JavaScript
- **Frontend-only application**: No backend server required - runs entirely in the browser
- **Leaflet.js integration**: Uses Leaflet 1.9.4 for interactive mapping functionality
- **Local storage**: Authentication state and marker data are stored in browser localStorage
- **Photo handling**: Supports drag-and-drop photo uploads with client-side preview and storage

## Key Components

### Authentication System
- Simple password-based authentication stored in localStorage
- Authentication overlay prevents unauthorized marker addition
- Located in the `AuthOverlay` and related JavaScript functions

### Map Management
- Interactive Leaflet map with OpenStreetMap tiles
- Click-to-add marker functionality for authenticated users
- Custom marker styling with photo support
- Map initialization and event handling in the main JavaScript class

### Photo Upload System
- Drag-and-drop file upload interface
- Client-side image preview and validation
- Maximum 2 photos per marker, 5MB file size limit
- Base64 encoding for storage in localStorage

## Development

### Running the Application
- Open `index.html` directly in a web browser
- No build process, server, or package manager required
- For local development, serve via any static file server if needed

### File Structure
- `index.html` - Complete application (HTML, CSS, JavaScript)
- `README.md` - Project description
- `LICENSE` - License information

### Authentication
- Default password can be modified in the JavaScript authentication logic
- Authentication state persists in localStorage across browser sessions

## Code Organization

The JavaScript is organized into:
- Main application class with map initialization
- Authentication handling methods
- Marker management (add, display, photo handling)
- Photo upload and preview functionality
- UI event handlers for drag-and-drop and modal interactions

Since this is a single-file application, all modifications should be made to the `index.html` file, taking care to maintain the embedded structure of HTML, CSS, and JavaScript.