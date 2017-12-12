<?php
namespace tcp;

final class Error
{

    /**
     * 心跳超时
     *
     * @var int
     */
    const heartBeatTimeout = 0xf1;

    /**
     * 登录超时
     *
     * @var int
     */
    const loginTimeOut = 0xf2;

    /**
     * 重复连接
     *
     * @var int
     */
    const connectMoreTimes = 0xf3;

    /**
     * 登录标签发送超时
     *
     * @var int
     */
    const sendLoginTagsTimeout = 0xf4;

    /**
     * 关门标签发送超时
     *
     * @var int
     */
    const sendCloseTagsTimeout = 0xf5;

    /**
     * 包长度不符
     *
     * @var int
     */
    const packageLenghError = 0xe1;

    /**
     * 校验和不符
     *
     * @var int
     */
    const packageCheckSumError = 0xe2;

    /**
     * 包头无效
     *
     * @var int
     */
    const packageHeaderError = 0xe3;

    /**
     * 包结构无效
     *
     * @var int
     */
    const packageStructureError = 0xe4;

    /**
     * 包结构解析失败
     *
     * @var int
     */
    const packageParseFailed = 0xe5;

    /**
     * 命令无效
     *
     * @var int
     */
    const invalidCommand = 0xe6;

    /**
     * DEVICE KEY ERROR
     *
     * @var int
     */
    const deviceKeyError = 0xe7;

    /**
     * 无效的请求，参数合法
     *
     * @var int
     */
    const invalidParameter = 0xe8;

    /**
     * 数据包解析失败
     *
     * @var int
     */
    const loadParseFailed = 0xa1;

    /**
     * 重复发送登录请求
     *
     * @var int
     */
    const loginMoreTimes = 0xa2;

    /**
     * 服务端内部错误
     *
     * @var int
     */
    const serverError = 0xa3;

    /**
     * 上送标签数量不符
     *
     * @var int
     */
    const tagsNumberUnmatch = 0xa4;

    /**
     * 未登录
     *
     * @var int
     */
    const deviceUnlogined = 0xa5;

    /**
     * 未准备就绪
     *
     * @var int
     */
    const hasNotBeReady = 0xa6;

    /**
     * 尚未发送登录标签
     *
     * @var int
     */
    const hasNotSendLoginTags = 0xa7;

    /**
     * 尚未发送关门标签
     *
     * @var int
     */
    const hasNotSendCloseTags = 0xa8;

    /**
     * 重复发送登录标签
     *
     * @var int
     */
    const sendLoginTagsRepeat = 0xa9;
}
