# Feature Specification: Creative Management System (MadData)

## 1. Overview
We are adding a "Creative Management" module to the Campaign system. 
Users need to upload sets of assets (Images/Videos) that match specific size requirements defined per campaign.
The system supports "Rotation Strategies" (how to pick which creative to show).

## 2. Data Models (Single Source of Truth)

### Campaign (Updates)
We need to store the required sizes for the campaign assets.
```typescript
interface Campaign {
  // ... existing fields (id, name, client, etc.)
  
  // Stored as a CSV string of sizes (e.g., "300x250,728x90,Mobile")
  // In UI: Managed via a Tag/Chip input field with Presets.
  requiredCreativeSizes: string; 
}
```

### Creative (New Entity)
A "Creative" is a container (Set) for multiple files (Assets).
```typescript
interface Creative {
  id: string;
  campaignId: string;
  name: string; // e.g., "Winter Sale Banner"
  landingPageUrl: string; // Default URL for all assets in this set
  isActive: boolean; // Toggle On/Off
  
  // Computed/Virtual fields for UI:
  // hasAllSizes: boolean; (Calculated based on Campaign.requiredCreativeSizes)
  // previewImage: string; (Thumbnail of the first available asset)
}
```

### CreativeAsset (New Entity)
The actual physical files uploaded.
```typescript
interface CreativeAsset {
  id: string;
  creativeId: string;
  type: 'image' | 'video';
  sizeLabel: string; // e.g., "300x250" or "1920x1080"
  fileUrl: string;
  fileName: string;
  // Metadata
  width?: number;
  height?: number;
}
```

### CampaignSettings (New / Update)
```typescript
interface CampaignCreativeSettings {
  campaignId: string;
  
  // Rotation Strategy
  // If 'even': Random/Round-robin distribution.
  // If 'ctr': Auto-optimization based on performance.
  rotationMode: 'even' | 'ctr'; 
}
```

## 3. UI/UX Requirements
### A. Campaign Creation Screen (Admin)
New Field: Required Creative Sizes.
Component: Tag Input (Chips).
Features:
- Users can type a size and hit enter to create a tag.
  - Presets Dropdown: Ability to select predefined sets (e.g., "Video Package" -> auto-fills [1920x1080, 300x250]).
  - Saved to DB as a comma-separated string.

### B. Creative Management List (Inside Campaign)
Entry: Accessed via "Manage Creatives" button on the main Campaigns list.
Header: Toggle Switch for Rotation Strategy (Even vs Auto Optimize).
List Columns:
- Status (Active/Inactive Toggle).
- Preview (Thumbnail).
- Name.
- Assets Status: Visual indicator.
  - Green Check [v] 300x250 if asset exists.
  - Red/Gray [!] 728x90 if missing.
  - Note: Saving is allowed even if assets are missing (Manual process).

### C. The Builder (Edit/Create Creative)
Inputs: Name, Landing Page URL.
Upload Area: Drag & Drop zone.
Logic:
- Display "Slots" (Cards) for each required size defined in the Campaign.
- When a file is uploaded, try to auto-match it to a slot based on dimensions (if image).
- If Video: Just match to video slots.
- Allow manual assignment if auto-match fails.
- Show "Missing" state for empty slots.

### D. Activity Log (Admin Dashboard)
Goal: Admins manually handle files, so they need to know when things change.
Track Events:
- CREATIVE_CREATED
- CREATIVE_DELETED
- ASSET_UPLOADED (Highlight this one)
- ASSET_DELETED
Display: A simple chronological table widget.

## 4. Technical Implementation Notes
- Validation: Non-blocking warnings. Do not prevent save if sizes are missing.
- Video: Treat as a single file (no poster image required).
- Rotation: Logic for 'ctr' optimization will be handled by the ad-serving engine, this UI only sets the flag.
