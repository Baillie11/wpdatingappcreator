# HedonX Match System

This document explains how the current HedonX matching system works in the plugin code today.

## Two Different "Match" Concepts

HedonX currently uses the word match in two different ways:

1. Suggested matches

These are the members shown on the Matches page. They come from the scoring engine in [includes/class-dsb-matching.php](../includes/class-dsb-matching.php).

2. Mutual matches

These happen when User A likes User B and User B also likes User A. They come from the likes system in [includes/class-dsb-likes.php](../includes/class-dsb-likes.php).

Those two systems are related, but they are not the same thing.

## Suggested Matches

The Matches page uses `DSB_Matching::get_matches()` to build a ranked list of recommended members.

### Step 1: Load the current member's preferences

The system reads the following profile data from user meta:

- `dsb_gender`
- `dsb_looking_for`
- `dsb_date_of_birth`
- `dsb_age_min`
- `dsb_age_max`
- `dsb_country`
- `dsb_state`
- `dsb_interests`
- `dsb_block_other_countries`
- `dsb_block_other_states`

If `age_min` or `age_max` are missing, the system defaults to `18` and `99`.

### Step 2: Build the candidate pool

Before scoring anyone, the system fetches up to `500` potential members.

Members are only considered if they:

- have the `dating_member` or `dating_premium` role
- are not the current user
- have an approved profile (`dsb_profile_approved = 1`)
- are not banned

They are also excluded if:

- the current member has blocked them
- they have blocked the current member

If the current member has location restrictions enabled:

- `block_other_countries = yes` limits candidates to the same country
- `block_other_states = yes` limits candidates to the same state

### Step 3: Score each candidate

Every candidate gets a score, then the results are sorted highest to lowest.

Only members with a score greater than `0` are returned.

## How the Score Is Calculated

The score is normalized to a final value between `0` and `100`.

### 1. Connection-type compatibility

Maximum weight: `40`

Despite some older labels in the admin UI, this part is currently driven by the `looking_for` field, not by a hard gender filter.

Rules:

- if both members share at least one `looking_for` value, they are compatible
- if either member selected `open_to_anything`, they are compatible
- if either side has not filled in `looking_for`, the system treats them as compatible instead of excluding them

If compatible, the pair receives the full `40` points.

### 2. Age compatibility

Maximum weight: `20`

This checks whether each member's age falls inside the other person's preferred age range.

Scoring:

- both sides fit each other's range: full score
- only one side fits: half score
- neither side fits: zero

### 3. Location compatibility

Maximum weight: `10`

This currently checks for the same country.

The code also compares state values, but the current result still effectively returns true based on country match.

### 4. Shared interests

Maximum weight: `30`

Interests are compared using Jaccard similarity:

$$
\text{interest score} = \frac{\text{shared interests}}{\text{total unique interests}}
$$

The plugin supports both:

- array-based interest values
- older comma-separated interest strings

### 5. Activity bonus

Maximum weight: `10`

The plugin reads `dsb_last_active`. If that is missing, it falls back to the user's registration date.

Activity score:

- active within 7 days: `1.0`
- active within 30 days: `0.5`
- active within 90 days: `0.25`
- older than 90 days: `0.1`

## Matching Modes

The admin setting `dsb_matching_mode` changes which score components are used.

### `simple`

Current code behavior:

- includes connection-type compatibility
- includes activity bonus
- does not include age scoring
- does not include location scoring
- does not include interests scoring

### `interests`

Current code behavior:

- includes connection-type compatibility
- includes age scoring
- includes location scoring
- includes interests scoring
- includes activity bonus

### `hybrid`

Current code behavior:

- includes connection-type compatibility
- includes age scoring
- includes location scoring
- includes interests scoring
- includes activity bonus

`hybrid` is the default.

## Important Note About the Admin Labels

Some admin wording still describes matching in older terms such as gender, age, and location preferences only.

The current code behaves slightly differently:

- compatibility is driven by the `looking_for` field
- `simple` mode still includes the activity bonus
- `simple` mode does not currently score age or location

If you publish this explanation on the site, it should be treated as the current implementation, not the original intended wording.

## Mutual Matches From Likes

The likes system is separate from the recommendation engine.

### How it works

When a user likes another member:

- a row is inserted into the `dsb_likes` table
- if the target user has already liked them back, the pair becomes a mutual match

A mutual match means:

- User A liked User B
- User B liked User A

The plugin checks this with `DSB_Likes::is_mutual_match()`.

### Where mutual matches appear

Mutual likes are shown in the Likes area using `DSB_Likes::get_mutual_matches()`.

This is different from the Matches page, which uses the recommendation score from `DSB_Matching::get_matches()`.

## Messaging and Matches

Private messaging can optionally be restricted by mutual likes.

If the admin setting `dsb_require_mutual_like` is enabled:

- users can only send private messages to members they have matched with through mutual likes
- suggested matches from the scoring engine do not automatically unlock messaging

This rule is enforced in [includes/class-dsb-messaging.php](../includes/class-dsb-messaging.php).

## Summary

In short:

- the Matches page shows scored recommendations
- the Likes page can show mutual matches created by two-way likes
- mutual likes can be used as a messaging gate if enabled
- blocked, banning, and approval status are applied before recommendations are shown

## Code References

- [includes/class-dsb-matching.php](../includes/class-dsb-matching.php)
- [includes/class-dsb-likes.php](../includes/class-dsb-likes.php)
- [includes/class-dsb-messaging.php](../includes/class-dsb-messaging.php)
- [includes/class-dsb-frontend.php](../includes/class-dsb-frontend.php)
- [includes/class-dsb-admin.php](../includes/class-dsb-admin.php)