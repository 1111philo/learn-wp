/**
 * 1111 Learn — Block Editor Sidebar Panel
 *
 * Registers a "Learn Feedback" sidebar panel for `learn` posts in the
 * block editor. Uses @wordpress/* packages bundled with WordPress.
 *
 * @package Learn
 */

/* global wp */

( function () {
	'use strict';

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.select;
	var PanelBody = wp.components.PanelBody;
	var TextareaControl = wp.components.TextareaControl;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;

	/**
	 * Sidebar component for Learn post feedback and regeneration.
	 */
	function LearnSidebar() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		} );

		var postId = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		} );

		var _state = useState( '' );
		var feedback = _state[0];
		var setFeedback = _state[1];

		var _loading = useState( false );
		var isSubmitting = _loading[0];
		var setIsSubmitting = _loading[1];

		var _notice = useState( null );
		var notice = _notice[0];
		var setNotice = _notice[1];

		var _meta = useState( null );
		var meta = _meta[0];
		var setMeta = _meta[1];

		var _version = useState( 1 );
		var version = _version[0];
		var setVersion = _version[1];

		// Fetch post meta on mount.
		useEffect( function () {
			if ( ! postId || 'learn' !== postType ) {
				return;
			}

			wp.apiFetch( {
				path: '/wp/v2/learn/' + postId + '?context=edit',
			} ).then( function ( post ) {
				if ( post.meta ) {
					setMeta( post.meta );
					setVersion( post.meta._1111_version || 1 );
				}
			} );
		}, [ postId, postType ] );

		// Only render for learn posts.
		if ( 'learn' !== postType ) {
			return null;
		}

		/**
		 * Submit feedback for regeneration.
		 */
		function onSubmitFeedback() {
			if ( ! feedback.trim() ) {
				setNotice( { status: 'error', message: 'Please enter feedback.' } );
				return;
			}

			setIsSubmitting( true );
			setNotice( null );

			wp.apiFetch( {
				path: '/wp/v2/1111-learn/feedback',
				method: 'POST',
				data: {
					post_id: postId,
					feedback: feedback,
				},
			} ).then( function () {
				setFeedback( '' );
				setNotice( { status: 'success', message: 'Feedback submitted. Regeneration queued.' } );
				setIsSubmitting( false );
			} ).catch( function ( error ) {
				setNotice( {
					status: 'error',
					message: error.message || 'Failed to submit feedback.',
				} );
				setIsSubmitting( false );
			} );
		}

		/**
		 * Request regeneration without feedback.
		 */
		function onRegenerate() {
			setIsSubmitting( true );
			setNotice( null );

			wp.apiFetch( {
				path: '/wp/v2/1111-learn/regenerate',
				method: 'POST',
				data: {
					post_id: postId,
				},
			} ).then( function () {
				setNotice( { status: 'success', message: 'Regeneration started.' } );
				setIsSubmitting( false );
			} ).catch( function ( error ) {
				setNotice( {
					status: 'error',
					message: error.message || 'Failed to regenerate.',
				} );
				setIsSubmitting( false );
			} );
		}

		// Build sidebar content.
		var children = [];

		// Notice.
		if ( notice ) {
			children.push(
				el( Notice, {
					key: 'notice',
					status: notice.status,
					isDismissible: true,
					onRemove: function () {
						setNotice( null );
					},
				}, notice.message )
			);
		}

		// Version indicator.
		children.push(
			el( 'p', { key: 'version', className: 'learn-version-indicator' },
				'Version: ' + version
			)
		);

		// Post meta info.
		if ( meta ) {
			children.push(
				el( PanelBody, { key: 'meta-panel', title: 'Content Info', initialOpen: true },
					meta._1111_activity_type
						? el( 'p', null,
							'Type: ',
							el( 'span', { className: 'learn-activity-badge learn-activity-badge--' + meta._1111_activity_type },
								meta._1111_activity_type
							)
						)
						: null,
					meta._1111_xp_value
						? el( 'p', null, 'XP: ' + meta._1111_xp_value )
						: null,
					meta._1111_milestone
						? el( 'p', null, 'Milestone: ' + meta._1111_milestone )
						: null,
					meta._1111_reviewer_verdict
						? el( 'p', null, 'Verdict: ' + meta._1111_reviewer_verdict )
						: null
				)
			);
		}

		// Feedback panel.
		children.push(
			el( PanelBody, { key: 'feedback-panel', title: 'Feedback', initialOpen: true },
				el( TextareaControl, {
					label: 'Provide feedback for regeneration',
					value: feedback,
					onChange: setFeedback,
					disabled: isSubmitting,
					rows: 4,
				} ),
				el( 'div', { style: { display: 'flex', gap: '8px' } },
					el( Button, {
						variant: 'primary',
						onClick: onSubmitFeedback,
						disabled: isSubmitting,
					}, isSubmitting ? el( Spinner, null ) : 'Submit Feedback' ),
					el( Button, {
						variant: 'secondary',
						onClick: onRegenerate,
						disabled: isSubmitting,
					}, 'Regenerate' )
				)
			)
		);

		// Instructions/rubric panel.
		if ( meta && ( meta._1111_instructions || meta._1111_rubric ) ) {
			children.push(
				el( PanelBody, { key: 'details-panel', title: 'Activity Details', initialOpen: false },
					meta._1111_instructions
						? el( 'div', null,
							el( 'strong', null, 'Instructions' ),
							el( 'p', null, meta._1111_instructions )
						)
						: null,
					meta._1111_rubric
						? el( 'div', null,
							el( 'strong', null, 'Rubric' ),
							el( 'p', null, meta._1111_rubric )
						)
						: null
				)
			);
		}

		return el( Fragment, null,
			el( PluginSidebarMoreMenuItem, {
				target: 'learn-feedback-sidebar',
				icon: 'welcome-learn-more',
			}, 'Learn Feedback' ),
			el( PluginSidebar, {
				name: 'learn-feedback-sidebar',
				title: 'Learn Feedback',
				icon: 'welcome-learn-more',
			}, children )
		);
	}

	// Register the plugin sidebar.
	registerPlugin( '1111-learn-sidebar', {
		render: LearnSidebar,
	} );
} )();
