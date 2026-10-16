import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/community.dart';
import 'package:octa_fit/domain/entities/friendship.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';
import 'package:octa_fit/domain/repositories/friendship_repository.dart';
import 'package:octa_fit/presentation/community/cubit/community_feed_cubit.dart';
import 'package:octa_fit/presentation/community/cubit/create_post_cubit.dart';
import 'package:octa_fit/presentation/community/cubit/post_detail_cubit.dart';

class MockCommunityRepository extends Mock implements CommunityRepository {}

class MockFriendshipRepository extends Mock implements FriendshipRepository {}

void main() {
  late MockCommunityRepository repository;
  late MockFriendshipRepository friendshipRepository;

  final author = CommunityAuthor(id: 2, name: 'Adeeb');
  final post = CommunityPost(
    id: 1,
    content: 'Great workout today!',
    likeCount: 2,
    commentCount: 0,
    isLiked: false,
    isOwner: true,
    author: author,
    createdAt: '2026-09-20T18:30:00Z',
  );

  final page = CommunityPostPage(
    items: [post],
    currentPage: 1,
    lastPage: 2,
    perPage: 15,
    total: 16,
  );

  final story = CommunityStory(
    id: 9,
    mediaUrl: 'https://cdn.octafit.test/s.jpg',
    isOwner: true,
    author: author,
  );

  final friendsPage = const CommunityUserPage(
    items: [],
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0,
    pendingIncomingCount: 0,
  );

  setUp(() {
    repository = MockCommunityRepository();
    friendshipRepository = MockFriendshipRepository();
  });

  group('CommunityFeedCubit', () {
    blocTest<CommunityFeedCubit, CommunityFeedState>(
      'loads feed and stories',
      build: () {
        when(() => repository.getFeed(page: any(named: 'page'), perPage: any(named: 'perPage')))
            .thenAnswer((_) async => page);
        when(() => repository.getActiveStories()).thenAnswer((_) async => [story]);
        when(() => friendshipRepository.getFriends(page: any(named: 'page')))
            .thenAnswer((_) async => friendsPage);
        return CommunityFeedCubit(repository, friendshipRepository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const CommunityFeedLoading(),
        isA<CommunityFeedLoaded>()
            .having((s) => s.posts.length, 'posts', 1)
            .having((s) => s.stories.length, 'stories', 1),
      ],
    );

    blocTest<CommunityFeedCubit, CommunityFeedState>(
      'emits error on failure',
      build: () {
        when(() => repository.getFeed(page: any(named: 'page'), perPage: any(named: 'perPage')))
            .thenThrow(ApiException('down', messageKey: 'error_network'));
        when(() => repository.getActiveStories()).thenAnswer((_) async => []);
        when(() => friendshipRepository.getFriends(page: any(named: 'page')))
            .thenAnswer((_) async => friendsPage);
        return CommunityFeedCubit(repository, friendshipRepository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const CommunityFeedLoading(),
        const CommunityFeedError('error_network'),
      ],
    );

    blocTest<CommunityFeedCubit, CommunityFeedState>(
      'toggle like updates post',
      build: () {
        when(() => repository.likePost(1))
            .thenAnswer((_) async => const LikeResult(liked: true, likeCount: 3));
        return CommunityFeedCubit(repository, friendshipRepository);
      },
      seed: () => CommunityFeedLoaded(
        posts: [post],
        stories: const [],
        currentPage: 1,
        hasMore: false,
      ),
      act: (cubit) => cubit.toggleLike(1),
      expect: () => [
        isA<CommunityFeedLoaded>().having((s) => s.posts.first.isLiked, 'liked', true),
      ],
    );
  });

  group('CreatePostCubit', () {
    blocTest<CreatePostCubit, CreatePostState>(
      'creates post',
      build: () {
        when(() => repository.createPost(content: any(named: 'content'), imageUrl: any(named: 'imageUrl')))
            .thenAnswer((_) async => post);
        return CreatePostCubit(repository);
      },
      act: (cubit) => cubit.submit('Great workout today!'),
      expect: () => [
        const CreatePostSubmitting(),
        CreatePostSuccess(post),
      ],
    );
  });

  group('PostDetailCubit', () {
    blocTest<PostDetailCubit, PostDetailState>(
      'loads post',
      build: () {
        when(() => repository.getPost(1)).thenAnswer((_) async => post);
        return PostDetailCubit(repository);
      },
      act: (cubit) => cubit.load(1),
      expect: () => [
        const PostDetailLoading(),
        PostDetailLoaded(post: post),
      ],
    );
  });
}
